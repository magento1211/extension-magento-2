<?php


namespace Emartech\Emarsys\Model\Api;


use Emartech\Emarsys\Api\ConfigApiInterface;
use Emartech\Emarsys\Api\Data\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class ConfigApi implements ConfigApiInterface
{
  protected $defaultConfig = [
  'collectCustomerEvents' => 'disabled',
  'collectSalesEvents' => 'disabled',
  'injectSnippet' => 'disabled',
  'merchantId' => null,
  'webTrackingSnippetUrl' => null
  ];

  /** @var ScopeConfigInterface */
  protected $scopeConfig;

  /** @var WriterInterface */
  protected $configWriter;

  public function __construct(
    WriterInterface $configWriter,
    ScopeConfigInterface $scopeConfig
  )
  {
    $this->scopeConfig = $scopeConfig;
    $this->configWriter = $configWriter;
  }

  /**
   * @param int $websiteId
   * @param ConfigInterface $config
   * @return mixed
   */
  public function set(
    $websiteId,
    ConfigInterface $config
  )
  {
    foreach ($config->getData() as $key => $value) {
      $this->configWriter->save('emartech/emarsys/config/' . $key, $value, 'website', $websiteId);
    }
    return 'OK';
  }

  /**
   * @param int $websiteId
   * @return mixed
   */
  public function setDefault($websiteId)
  {
    foreach ($this->defaultConfig as $key => $value) {
      $this->configWriter->save('emartech/emarsys/config/' . $key, $value, 'website', $websiteId);
    }
    return 'OK';
  }
}
