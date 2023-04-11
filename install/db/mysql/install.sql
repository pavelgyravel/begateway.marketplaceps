create table if not exists b_bemarketplaceps_order_data (
	ID int(18) not null auto_increment,
  PS_INVOICE_ID varchar(255) not null,
  PARAMS text not null,

	PRIMARY KEY (ID)
);

ALTER TABLE b_bemarketplaceps_order_data ADD INDEX INDEX_PS_INVOICE_ID(PS_INVOICE_ID)