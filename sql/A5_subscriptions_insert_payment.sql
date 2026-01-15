INSERT INTO
    `payments`

    ( `subscription_id`, `duration`, `amount`, `vat`, `when`, `success`, `auto`, `amount_nexi`, `codTrans`, `user_id`, `ip`, `ua`, `from`, `to`, `pan`, `nexi_id`, `nexi_key` )

VALUES
    ( {{subscription_id}}, {{duration}}, {{amount}}, {{vat}}, {{when}}, {{success}}, 1, {{amount_nexi}}, {{codTrans}}, 0, '', '', {{from}}, {{to}}, {{pan}}, {{nexi_id}}, {{nexi_key}} )
