-- Original tblProductData schema (without stock/price columns).
-- The two new columns (intStock, intPricePence) are added by the Doctrine migration.
-- importTest_test is used by the test suite (Doctrine appends the _test suffix in APP_ENV=test).

CREATE DATABASE IF NOT EXISTS importTest_test;
GRANT ALL ON importTest_test.* TO 'app'@'%';

CREATE TABLE IF NOT EXISTS tblProductData (
  intProductDataId int(10) unsigned NOT NULL AUTO_INCREMENT,
  strProductName   varchar(50)      NOT NULL,
  strProductDesc   varchar(255)     NOT NULL,
  strProductCode   varchar(10)      NOT NULL,
  dtmAdded         datetime         DEFAULT NULL,
  dtmDiscontinued  datetime         DEFAULT NULL,
  stmTimestamp     timestamp        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (intProductDataId),
  UNIQUE KEY uq_product_code (strProductCode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores product data';
