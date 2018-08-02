<?php


namespace Emartech\Emarsys\Helper;


use Emartech\Emarsys\Model\ResourceModel\Event;
use Emartech\Emarsys\Api\Data\ConfigInterface;
use Emartech\Emarsys\Model\EventFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Newsletter\Model\Subscriber;
use Psr\Log\LoggerInterface;

class CustomerEventHandler extends AbstractHelper
{
  protected $logger;
  protected $customerFactory;
  protected $eventFactory;
  protected $eventResource;
  protected $subscriber;
  /** @var ConfigReader */
  protected $configReader;

  public function __construct(
    ConfigReader $configReader,
    CustomerFactory $customerFactory,
    EventFactory $eventFactory,
    Event $eventResource,
    Subscriber $subscriber,
    LoggerInterface $logger
  )
  {
    $this->customerFactory = $customerFactory;
    $this->eventFactory = $eventFactory;
    $this->eventResource = $eventResource;
    $this->logger = $logger;
    $this->subscriber = $subscriber;
    $this->configReader = $configReader;
  }

  /**
   * @param string $type
   * @param int $customerId
   * @throws \Exception
   * @throws \Magento\Framework\Exception\AlreadyExistsException
   */
  public function store($type, $customerId)
  {
    if (!$this->configReader->isEnabled(ConfigInterface::CUSTOMER_EVENTS)) return;

    /** @var Customer $customer */
    $customer = $this->customerFactory->create()->load($customerId);
    $customerData = $customer->toArray();
    $customerData['id'] = $customerData['entity_id'];

    if ($customer->getDefaultBillingAddress()) {
      $customerData['billing_address'] = $customer->getDefaultBillingAddress()->toArray();
    }
    if ($customer->getDefaultShippingAddress()) {
      $customerData['shipping_address'] = $customer->getDefaultShippingAddress()->toArray();
    }

    $subscription = $this->subscriber->loadByCustomerId($customerId);
    $customerData['accepts_marketing'] = $subscription->getStatus();

    /** @var \Emartech\Emarsys\Model\Event $eventModel */
    $eventModel = $this->eventFactory->create();
    $eventModel->setData('event_type', $type);
    $eventModel->setData('event_data', json_encode($customerData));
    $this->eventResource->save($eventModel);

    $this->logger->info('event_type: '. $type . ', event_data: '.json_encode($customerData));
  }
}