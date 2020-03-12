'use strict';

const { getProducts } = require('../fixtures/products');

describe('Products endpoint', function() {
  before(async function() {
    await this.magentoApi.execute('attributes', 'set', {
      websiteId: 0,
      type: 'product',
      attributeCodes: ['emarsys_test_fuel_type']
    });
  });

  after(async function() {
    await this.magentoApi.execute('attributes', 'set', {
      websiteId: 0,
      type: 'product',
      attributeCodes: []
    });
  });

  it('returns product count and products according to page and page_size', async function() {
    const page = 3;
    const limit = 10;

    const { products: expectedProducts, productCount: expectedProductCount } = getProducts(
      this.hostname,
      page,
      limit,
      this.magentoVersion,
      this.magentoEdition
    );

    const expectedProduct = expectedProducts[0];

    await this.createProduct({
      sku: expectedProduct.sku,
      custom_attributes: {
        special_price: 2
      }
    });

    const { products, productCount } = await this.magentoApi.execute('products', 'get', { page, limit, storeIds: [1] });

    const product = products[0];

    expect(products.length).to.equal(expectedProducts.length);
    expect(productCount).to.equal(expectedProductCount);

    ['entity_id', 'type', 'sku', 'qty', 'is_in_stock', 'images'].forEach(key => {
      expect({ key, value: product[key] }).to.eql({ key, value: expectedProduct[key] });
    });

    expect(product.children_entity_ids).to.be.an('array');
    expect(product.categories[0]).to.equal(expectedProduct.categories[0]);
    expect(product.categories[1]).to.equal(expectedProduct.categories[1]);

    const storeLevelProduct = product.store_data.find(store => store.store_id === 1);
    [
      'name',
      'price',
      'webshop_price',
      'original_webshop_price',
      'original_display_price',
      'display_webshop_price',
      'link',
      'status',
      'description'
    ].forEach(key => {
      expect({ key, value: storeLevelProduct[key] }).to.eql({ key, value: expectedProduct.store_data[0][key] });
    });
  });

  it('returns child entities for configurable products', async function() {
    let page;
    switch (this.magentoVersion) {
      case '2.3.0':
        page = 68;
        break;
      case '2.3.1':
        page = this.magentoEdition === 'Enterprise' ? 70 : 68;
        break;
      case '2.1.9':
        page = this.magentoEdition === 'Enterprise' ? 69 : 68;
        break;
      case '2.3.2':
        page = 68; //this.magentoEdition === 'Enterprise' ? 70 : 68;
        break;
      case '2.3.3':
        page = 68; //this.magentoEdition === 'Enterprise' ? 70 : 68;
        break;
      default:
        page = 67;
    }

    const limit = 1;

    const { products } = await this.magentoApi.execute('products', 'get', { page, limit, storeIds: [1] });
    const { products: expectedProducts } = getProducts(
      this.hostname,
      67,
      limit,
      this.magentoVersion,
      this.magentoEdition
    );
    const product = products[0];
    const expectedProduct = expectedProducts[0];

    ['type', 'children_entity_ids'].forEach(key => {
      expect(product[key]).to.eql(expectedProduct[key]);
    });
  });

  it('returns extra_fields for products', async function() {
    const { products: originalProducts } = await this.magentoApi.execute('products', 'get', {
      page: 1,
      limit: 1,
      storeIds: [1]
    });

    await this.magentoApi.put({
      path: `/rest/V1/products/${originalProducts[0].sku}`,
      payload: {
        product: {
          custom_attributes: [
            {
              attribute_code: 'emarsys_test_fuel_type',
              value: 'gasoline'
            },
            {
              attribute_code: 'emarsys_test_number_of_seats',
              value: 6
            }
          ]
        }
      }
    });

    const { products } = await this.magentoApi.execute('products', 'get', { page: 1, limit: 1, storeIds: [1] });
    const updatedProduct = products[0];

    expect(updatedProduct.store_data[0].extra_fields[0].key).to.be.equal('emarsys_test_fuel_type');
    expect(updatedProduct.store_data[0].extra_fields[0].value).to.be.equal('gasoline');
    expect(updatedProduct.store_data[0].extra_fields.length).to.be.equal(1);
  });

  it('returns different prices for the same product on multiple websites', async function() {
    const sku = '24-MB01';

    const extensionAttributes = this.magentoVersion.startsWith('2.1')
      ? {}
      : { extension_attributes: { website_ids: [1, 2] } };

    await this.magentoApi.post({
      path: '/rest/default/V1/products',
      payload: {
        product: {
          sku,
          price: 111,
          ...extensionAttributes
        }
      }
    });

    await this.magentoApi.post({
      path: '/rest/second_store/V1/products',
      payload: {
        product: {
          sku,
          price: 222,
          ...extensionAttributes
        }
      }
    });

    const { products } = await this.magentoApi.execute('products', 'get', { page: 1, limit: 10, storeIds: [1, 2] });

    const product = products.find(product => product.sku === sku);
    const defaultStoreItem = product.store_data.find(storeData => storeData.store_id === 1);
    const secondStoreItem = product.store_data.find(storeData => storeData.store_id === 2);

    expect(defaultStoreItem.webshop_price).to.eql(111);
    expect(defaultStoreItem.display_webshop_price).to.eql(222);
    expect(defaultStoreItem.original_display_webshop_price).to.eql(222);

    expect(secondStoreItem.webshop_price).to.eql(222);
    expect(secondStoreItem.display_webshop_price).to.eql(444);
    expect(secondStoreItem.original_display_webshop_price).to.eql(444);
  });

  it('returns different original prices for the same product on multiple websites', async function() {
    const sku = '24-MB04';

    const extensionAttributes = this.magentoVersion.startsWith('2.1')
      ? {}
      : { extension_attributes: { website_ids: [1, 2] } };

    await this.magentoApi.post({
      path: '/rest/second_store/V1/products',
      payload: {
        product: {
          sku,
          price: 1000,
          ...extensionAttributes
        }
      }
    });

    const { products } = await this.magentoApi.execute('products', 'get', { page: 1, limit: 10, storeIds: [1, 2] });

    const product = products.find(product => product.sku === sku);
    const defaultStoreItem = product.store_data.find(storeData => storeData.store_id === 1);
    const secondStoreItem = product.store_data.find(storeData => storeData.store_id === 2);

    expect(defaultStoreItem.display_webshop_price).to.eql(64);
    expect(defaultStoreItem.original_display_webshop_price).to.eql(64);
    expect(secondStoreItem.display_webshop_price).to.eql(64);
    expect(secondStoreItem.original_display_webshop_price).to.eql(2000);
  });

  context('out of stock', function() {
    const sku = '24-MB03';
    before(async function() {
      await this.magentoApi.put({
        path: `/rest/V1/products/${sku}/stockItems/1`,
        payload: {
          stockItem: {
            qty: 0
          }
        }
      });
    });

    after(async function() {
      await this.magentoApi.put({
        path: `/rest/V1/products/${sku}/stockItems/1`,
        payload: {
          stockItem: {
            qty: 100,
            is_in_stock: true
          }
        }
      });
    });

    it('should return out of stock products', async function() {
      const { products } = await this.magentoApi.execute('products', 'get', { page: 1, limit: 3, storeIds: [1, 2] });
      const product = products.find(product => product.sku === sku);

      expect(products.length).to.be.equal(3);
      expect(product).not.to.be.undefined;
    });
  });
});
