'use strict';

const getLastEvent = async (db) =>
  await db.select().from(`${tablePrefix}emarsys_events_data`).orderBy('event_id', 'desc').first();

const getAllEvents = async (db) =>
  await db.select().from(`${tablePrefix}emarsys_events_data`).orderBy('event_id', 'desc');

const createNewOrder = async (magentoApi, customer, localCartItem) => {
  const { data: cartId } = await magentoApi.post({
    path: `/index.php/rest/V1/customers/${customer.entityId}/carts`
  });

  await magentoApi.post({
    path: `/index.php/rest/V1/carts/${cartId}/items`,
    payload: {
      cartItem: Object.assign(localCartItem, { quote_id: cartId })
    }
  });

  await magentoApi.post({
    path: `/index.php/rest/V1/carts/${cartId}/shipping-information`,
    payload: {
      addressInformation: {
        shipping_address: {
          region: 'New York',
          region_id: 43,
          region_code: 'NY',
          country_id: 'US',
          street: ['123 Oak Ave'],
          postcode: '10577',
          city: 'Purchase',
          firstname: 'Jane',
          lastname: 'Doe',
          email: 'jdoe@example.com',
          telephone: '512-555-1111'
        },
        billing_address: {
          region: 'New York',
          region_id: 43,
          region_code: 'NY',
          country_id: 'US',
          street: ['123 Oak Ave'],
          postcode: '10577',
          city: 'Purchase',
          firstname: 'Jane',
          lastname: 'Doe',
          email: 'jdoe@example.com',
          telephone: '512-555-1111'
        },
        shipping_carrier_code: 'flatrate',
        shipping_method_code: 'flatrate'
      }
    }
  });

  const { data: orderId } = await magentoApi.put({
    path: `/index.php/rest/V1/carts/${cartId}/order`,
    payload: {
      paymentMethod: {
        method: 'checkmo'
      }
    }
  });

  return { cartId, orderId };
};

const fulfillOrder = async (magentoApi, orderId) => {
  await magentoApi.post({
    path: `/index.php/rest/V1/order/${orderId}/invoice`,
    payload: {
      capture: true
    }
  });

  await magentoApi.post({
    path: `/index.php/rest/V1/order/${orderId}/ship`
  });
};

const changeOrderStatus = async (magentoApi, orderId, orderStatus, orderState) => {
  await magentoApi.post({
    path: '/index.php/rest/V1/orders',
    payload: {
      entity: {
        entity_id: orderId,
        status: orderStatus,
        state: orderState
      }
    }
  });
};

const refundOnePieceFromFirstItemOfOrder = async (magentoApi, orderId) => {
  const { items } = await magentoApi.get({ path: `/index.php/rest/V1/orders/${orderId}` });
  const itemId = items[0].item_id;

  await magentoApi.post({
    path: `/index.php/rest/V1/order/${orderId}/refund`,
    payload: {
      items: [
        {
          order_item_id: itemId,
          qty: 1
        }
      ],
      notify: false
    }
  });
};

let tablePrefix;

describe('Order events', function () {
  let localCartItem;
  before(function () {
    tablePrefix = this.getTableName('');
    localCartItem = this.localCartItem;
  });
  context('setting enabled', function () {
    before(async function () {
      await this.magentoApi.execute('config', 'set', {
        websiteId: 1,
        config: { collectSalesEvents: 'enabled', collectMarketingEvents: 'disabled', magentoSendEmail: 'disabled' }
      });
    });

    it('should create orders/new event and an orders/fulfilled', async function () {
      const { orderId } = await createNewOrder(this.magentoApi, this.customer, localCartItem);

      const { event_type: createEventType, event_data: createEventPayload } = await getLastEvent(this.db);
      const createdEventData = JSON.parse(createEventPayload);
      const simpleItem = createdEventData.items[1];

      expect(createEventType).to.be.equal('orders/create');
      expect(simpleItem.parent_item).not.to.be.empty;
      expect(createdEventData.addresses).to.have.property('shipping');
      expect(createdEventData.addresses).to.have.property('billing');

      await fulfillOrder(this.magentoApi, orderId);

      const { event_type: fulfillEventType } = await getLastEvent(this.db);

      expect(fulfillEventType).to.be.equal('orders/fulfilled');
    });

    it('should not log orders/fulfilled when an order re-enters the complete state', async function () {
      const { orderId } = await createNewOrder(this.magentoApi, this.customer, localCartItem);
      await fulfillOrder(this.magentoApi, orderId);
      await changeOrderStatus(this.magentoApi, orderId, 'test_status', 'complete');

      const events = await getAllEvents(this.db);
      const fulfilledOrders = events.filter((event) => event.event_type === 'orders/fulfilled');

      expect(fulfilledOrders.length).to.be.equal(1);

      const eventData = JSON.parse(fulfilledOrders[0].event_data);
      expect(eventData.status).to.be.equal('complete');
    });

    it('should not log orders/fulfilled when a partial refund occurs', async function () {
      const { orderId } = await createNewOrder(this.magentoApi, this.customer, localCartItem);
      await fulfillOrder(this.magentoApi, orderId);

      await this.dbCleaner.resetEmarsysEventsData();

      await refundOnePieceFromFirstItemOfOrder(this.magentoApi, orderId);

      const events = await getAllEvents(this.db);

      const fulfilledOrders = events.filter((event) => event.event_type === 'orders/fulfilled');
      expect(fulfilledOrders.length).to.be.equal(0);

      const refundEvents = events.filter((event) => event.event_type === 'refunds/fulfilled');
      expect(refundEvents.length).to.be.equal(1);
      const refundData = JSON.parse(refundEvents[0].event_data);
      expect(refundData.order_id).to.be.equal(orderId);
    });

    it('should not group refunds/fulfilled events', async function () {
      const { orderId } = await createNewOrder(this.magentoApi, this.customer, localCartItem);
      await fulfillOrder(this.magentoApi, orderId);

      await refundOnePieceFromFirstItemOfOrder(this.magentoApi, orderId);
      await refundOnePieceFromFirstItemOfOrder(this.magentoApi, orderId);

      const events = await getAllEvents(this.db);
      const refundEvents = events.filter((event) => event.event_type === 'refunds/fulfilled');

      expect(refundEvents.length).to.be.equal(2);
    });

    context('store is not enabled', function () {
      before(async function () {
        await this.clearStoreSettings();
      });

      after(async function () {
        await this.setDefaultStoreSettings();
      });

      it('should not create event', async function () {
        await this.turnOffEverySetting(1);

        await createNewOrder(this.magentoApi, this.customer, localCartItem);
        const createEvent = await getLastEvent(this.db);

        expect(createEvent).to.be.undefined;
      });
    });
  });

  context('setting disabled', function () {
    it('should not create event', async function () {
      await this.turnOffEverySetting(1);
      await createNewOrder(this.magentoApi, this.customer, localCartItem);

      const createEvent = await getLastEvent(this.db);

      expect(createEvent).to.be.undefined;
    });
  });
});
