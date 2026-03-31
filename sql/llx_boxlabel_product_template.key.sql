-- Copyright (C) 2026 DPG Supply
--
-- Per-product label template indexes
--

ALTER TABLE llx_boxlabel_product_template ADD UNIQUE INDEX uk_boxlabel_product_template (fk_product, entity);
ALTER TABLE llx_boxlabel_product_template ADD INDEX idx_boxlabel_product_template_fk_product (fk_product);
