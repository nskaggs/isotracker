SELECT MAX(id) FROM qatracker_bug;
SELECT last_value FROM qatracker_bug_id_seq;

SELECT MAX(id) FROM qatracker_build;
SELECT last_value FROM qatracker_build_id_seq;

SELECT MAX(id) FROM qatracker_launchpad_bug;
SELECT last_value FROM qatracker_launchpad_bug_id_seq;

SELECT MAX(id) FROM qatracker_milestone;
SELECT last_value FROM qatracker_milestone_id_seq;

SELECT MAX(id) FROM qatracker_milestone_series;
SELECT last_value FROM qatracker_milestone_series_id_seq;

SELECT MAX(id) FROM qatracker_product;
SELECT last_value FROM qatracker_product_id_seq;

SELECT MAX(id) FROM qatracker_product_download;
SELECT last_value FROM qatracker_product_download_id_seq;

SELECT MAX(id) FROM qatracker_product_family;
SELECT last_value FROM qatracker_product_family_id_seq;

SELECT MAX(id) FROM qatracker_result;
SELECT last_value FROM qatracker_result_id_seq;

SELECT MAX(id) FROM qatracker_site;
SELECT last_value FROM qatracker_site_id_seq;

SELECT MAX(id) FROM qatracker_site_setting;
SELECT last_value FROM qatracker_site_setting_id_seq;

SELECT MAX(id) FROM qatracker_testcase;
SELECT last_value FROM qatracker_testcase_id_seq;

SELECT MAX(id) FROM qatracker_user_subscription;
SELECT last_value FROM qatracker_user_subscription_id_seq;
