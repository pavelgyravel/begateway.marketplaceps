<?
namespace BeGateway\Module\Marketplaceps;

class OrderStatuses {
  const ORDER_AWAITING_STATUS = 'EA';
  const ORDER_CANCELED_STATUS = 'EC';
}

class Events {
  const ORDER_STATUS_CHANGED_TO_EA = 'BEGATEWAY_MARKETPLACEPS_SALE_ORDER_STATUS_CHANGED_EA';
}
