-- Copyright (C) 2026 DPG Supply
--
-- Box Label indexes
--

ALTER TABLE llx_box_label ADD UNIQUE INDEX uk_box_label_ref (ref, entity);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_fk_product (fk_product);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_fk_mo (fk_mo);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_fk_product_lot (fk_product_lot);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_status (status);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_batch (batch);
ALTER TABLE llx_box_label ADD INDEX idx_box_label_serial_number (serial_number);
