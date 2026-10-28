我建立於test_db中，以final_XXX的格式命名。

CREATE TABLE `final_members` (
	`member_ID` INT(10) NOT NULL DEFAULT '0',
	`member_name` VARCHAR(10) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	`mail` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	`password` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_uca1400_ai_ci',
	`avatar_url` VARCHAR(255) NOT NULL DEFAULT 'default-avatar.png' COLLATE 'utf8mb4_uca1400_ai_ci',
	`is_active` TINYINT(1) NOT NULL DEFAULT '0',
	`verification_token` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_uca1400_ai_ci',
	`is_verified` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=未驗證, 1=已驗證',
	PRIMARY KEY (`member_ID`) USING BTREE
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
;

CREATE TABLE `final_products` (
	`product_ID` INT(10) NOT NULL AUTO_INCREMENT,
	`product_name` VARCHAR(30) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	`image_url` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	`price` INT(10) UNSIGNED NOT NULL,
	`sale_price` INT(10) NULL DEFAULT NULL COMMENT 'NULL無特價',
	`status` VARCHAR(20) NOT NULL DEFAULT '已發售' COLLATE 'utf8mb4_uca1400_ai_ci',
	`member_ID` INT(10) NOT NULL DEFAULT '0' COMMENT '對應創作者的會員ID',
	`product_info` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_uca1400_ai_ci',
	PRIMARY KEY (`product_ID`) USING BTREE,
	INDEX `FK_final_products_final_members` (`member_ID`) USING BTREE,
	CONSTRAINT `FK_final_products_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=10
;

CREATE TABLE `final_wishes` (
	`ID` INT(11) NOT NULL AUTO_INCREMENT,
	`member_ID` INT(11) NOT NULL,
	`product_ID` INT(11) NOT NULL,
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`ID`) USING BTREE,
	INDEX `member_ID` (`member_ID`) USING BTREE,
	INDEX `FK_final_wishes_final_products` (`product_ID`) USING BTREE,
	CONSTRAINT `FK_final_wishes_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT `FK_final_wishes_final_products` FOREIGN KEY (`product_ID`) REFERENCES `final_products` (`product_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=4
;

CREATE TABLE `final_reviews` (
	`review_ID` INT(10) NOT NULL AUTO_INCREMENT,
	`product_ID` INT(10) NOT NULL,
	`member_ID` INT(10) NOT NULL,
	`content` TEXT NOT NULL COMMENT '評論內容' COLLATE 'utf8mb4_uca1400_ai_ci',
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`review_ID`) USING BTREE,
	INDEX `product_ID` (`product_ID`) USING BTREE,
	INDEX `FK_final_reviews_final_members` (`member_ID`) USING BTREE,
	CONSTRAINT `FK_final_reviews_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_final_reviews_final_products` FOREIGN KEY (`product_ID`) REFERENCES `final_products` (`product_ID`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
;

CREATE TABLE `final_orders` (
	`order_ID` INT(10) NOT NULL DEFAULT '0',
	`member_ID` INT(10) NOT NULL DEFAULT '0',
	`total_price` INT(10) UNSIGNED NOT NULL DEFAULT '0',
	`status` CHAR(3) NOT NULL DEFAULT '' COMMENT '未/已付款' COLLATE 'utf8mb4_uca1400_ai_ci',
	`created_at` DATE NOT NULL,
	PRIMARY KEY (`order_ID`) USING BTREE,
	INDEX `FK_final_orders_final_members` (`member_ID`) USING BTREE,
	CONSTRAINT `FK_final_orders_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
;

CREATE TABLE `final_order_items` (
	`ID` INT(10) NOT NULL AUTO_INCREMENT,
	`order_ID` INT(10) NOT NULL DEFAULT '0',
	`product_ID` INT(10) NOT NULL DEFAULT '0',
	`price` INT(10) UNSIGNED NOT NULL,
	`status` CHAR(3) NOT NULL DEFAULT '' COMMENT '未/已付款' COLLATE 'utf8mb4_uca1400_ai_ci',
	PRIMARY KEY (`ID`) USING BTREE,
	INDEX `FK_final_order_items_final_orders` (`order_ID`) USING BTREE,
	INDEX `FK_final_order_items_final_products` (`product_ID`) USING BTREE,
	CONSTRAINT `FK_final_order_items_final_orders` FOREIGN KEY (`order_ID`) REFERENCES `final_orders` (`order_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT `FK_final_order_items_final_products` FOREIGN KEY (`product_ID`) REFERENCES `final_products` (`product_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
ROW_FORMAT=DYNAMIC
AUTO_INCREMENT=4
;

CREATE TABLE `final_carts` (
	`ID` INT(11) NOT NULL AUTO_INCREMENT,
	`product_ID` INT(10) NOT NULL DEFAULT '0',
	`member_ID` INT(10) NOT NULL DEFAULT '0',
	`image_url` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_uca1400_ai_ci',
	PRIMARY KEY (`ID`) USING BTREE,
	INDEX `FK_final_carts_final_products` (`product_ID`) USING BTREE,
	INDEX `FK_final_carts_final_members` (`member_ID`) USING BTREE,
	CONSTRAINT `FK_final_carts_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT `FK_final_carts_final_products` FOREIGN KEY (`product_ID`) REFERENCES `final_products` (`product_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=6
;

CREATE TABLE `final_wishes` (
	`ID` INT(11) NOT NULL AUTO_INCREMENT,
	`member_ID` INT(11) NOT NULL,
	`product_ID` INT(11) NOT NULL,
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`ID`) USING BTREE,
	INDEX `member_ID` (`member_ID`) USING BTREE,
	INDEX `FK_final_wishes_final_products` (`product_ID`) USING BTREE,
	CONSTRAINT `FK_final_wishes_final_members` FOREIGN KEY (`member_ID`) REFERENCES `final_members` (`member_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT `FK_final_wishes_final_products` FOREIGN KEY (`product_ID`) REFERENCES `final_products` (`product_ID`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8mb4_uca1400_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=4
;
