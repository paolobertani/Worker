INSERT INTO
    `payments`

    ( `subscription_id`, `duration`, `amount`, `vat`, `when`, `status`, `auto`, `amount_nexi`, `codTrans`, `user_id`, `ip`, `ua`, `from`, `to`, `pan`, `nexi_id`, `nexi_key`, `nexi_message` )

VALUES
    ( {{subscription_id}}, {{duration}}, {{amount}}, {{vat}}, {{when}}, {{status}}, 1, {{amount_nexi}}, {{codTrans}}, 0, '', '', {{from}}, {{to}}, {{pan}}, {{nexi_id}}, {{nexi_key}}, {{nexi_message}} )
