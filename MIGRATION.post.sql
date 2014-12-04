TRUNCATE authmap;
UPDATE users SET data='a:1:{s:17:"qatracker_api_key";s:20:"oamkeyWyewAgatAwJons";}' WHERE uid=29315;
TRUNCATE users_roles;
INSERT INTO users_roles VALUES (29315, 4);
INSERT INTO users_roles VALUES (1, 3);

INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Edubuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Kubuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Ubuntu Server', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Upgrade', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Xubuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Ubuntu Studio', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Netboot', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Lubuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Wubi', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Mythbuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Ubuntu Core', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Ubuntu', 0);
INSERT INTO qatracker_product_family (siteid, title, weight) VALUES (1, 'Ubuntu Server EC2', 0);

UPDATE qatracker_product SET familyid='12' WHERE status='0' AND siteid='1' AND title~'Ubuntu';
UPDATE qatracker_product SET familyid='1' WHERE status='0' AND siteid='1' AND title~'Edubuntu';
UPDATE qatracker_product SET familyid='2' WHERE status='0' AND siteid='1' AND title~'Kubuntu';
UPDATE qatracker_product SET familyid='3' WHERE status='0' AND siteid='1' AND title~'Ubuntu Server';
UPDATE qatracker_product SET familyid='4' WHERE status='0' AND siteid='1' AND title~'Upgrade';
UPDATE qatracker_product SET familyid='5' WHERE status='0' AND siteid='1' AND title~'Xubuntu';
UPDATE qatracker_product SET familyid='6' WHERE status='0' AND siteid='1' AND title~'Ubuntu Studio';
UPDATE qatracker_product SET familyid='7' WHERE status='0' AND siteid='1' AND title~'Netboot';
UPDATE qatracker_product SET familyid='8' WHERE status='0' AND siteid='1' AND title~'Lubuntu';
UPDATE qatracker_product SET familyid='9' WHERE status='0' AND siteid='1' AND title~'Wubi';
UPDATE qatracker_product SET familyid='10' WHERE status='0' AND siteid='1' AND title~'Mythbuntu';
UPDATE qatracker_product SET familyid='11' WHERE status='0' AND siteid='1' AND title~'Ubuntu Core';
UPDATE qatracker_product SET familyid='13' WHERE status='0' AND siteid='1' AND title~'EC2';

DELETE FROM qatracker_site WHERE id='6';
DELETE FROM qatracker_site_setting WHERE option IN ('default_product', 'default_milestone');
UPDATE qatracker_site SET userrole='authenticated user';
UPDATE qatracker_site SET adminrole='ubuntu iso admin' WHERE adminrole='ISO Admin';
UPDATE qatracker_site SET adminrole='ubuntu mozilla admin' WHERE adminrole='Mozilla Testing Admin';
UPDATE qatracker_site SET adminrole='ubuntu kernel admin' WHERE adminrole='Kernel Testing Admin';
UPDATE qatracker_site SET adminrole='ubuntu server admin' WHERE adminrole='Server Testing Admin';
UPDATE qatracker_site SET adminrole='ubuntu xorg admin' WHERE adminrole='Xorg Admin';
UPDATE qatracker_site SET adminrole='linaro iso admin' WHERE title='Linaro ISO Testing';