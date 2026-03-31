-- Copyright (C) 2026 DPG Supply
--
-- Per-product auto-label indexes
--

ALTER TABLE llx_boxlabel_product_auto ADD UNIQUE INDEX uk_boxlabel_product_auto (fk_product, entity);
ALTER TABLE llx_boxlabel_product_auto ADD INDEX idx_boxlabel_product_auto_fk_product (fk_product);
