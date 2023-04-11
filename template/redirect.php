<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

?>
<div id="begateway_marketplaceps_block">
  <form id="begateway_marketplaceps_form" action="<?= $params['form']['action']; ?>" method="<?= $params['form']['method']; ?>">
    <?php foreach ($params['form']['fields'] as &$field): ?>
      <input type="<?= $field['type']; ?>" name="<?= $field['name']; ?>" value="<?= $field['value']; ?>"/>
    <?php endforeach; ?>
    
    <p>Для осуществления оплаты в системе bePaid marketplace, нажмите кнопку "Перейти к оплате"</p>
    <input id="begateway_marketplaceps_form_submit" type="submit" value="Перейти к оплате"/>
  </form>
</div>

<style>
  #begateway_marketplaceps_block {
    margin-bottom: 20px;
  }

  #begateway_marketplaceps_form_submit {
    padding: .7em 1.5em;
    font-size: 16px;
    color: #fff;
    background: #54a05f;
    border: none;
    border-radius: 8px;
    cursor: pointer;
  }

  #begateway_marketplaceps_form_submit:hover {
    background: #72c77d; 
  }
</style>