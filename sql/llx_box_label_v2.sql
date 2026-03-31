-- Copyright (C) 2026 DPG Supply
--
-- Migration v2: Add date_archived for label archiving on shipment
--

ALTER TABLE llx_box_label ADD date_archived DATETIME AFTER date_creation;
ALTER TABLE llx_box_label ADD INDEX idx_box_label_date_archived (date_archived);
