alter table users drop column mode;
alter table users drop column sort;
alter table users drop column threshold;
alter table users drop column picture;
alter table users add column signature_format varchar;
alter table users add column picture smallint not null default 0;
update users set timezone=null;



alter table qawebsite_launchpad_bug rename to qatracker_launchpad_bug;
alter table qawebsite_launchpad_bug_id_seq rename to qatracker_launchpad_bug_id_seq;
alter table qatracker_launchpad_bug drop column mentoring;

alter table qawebsite_site rename to qatracker_site;
alter table qawebsite_site_id_seq rename to qatracker_site_id_seq;
alter table qatracker_site drop column developerrole;
alter table qatracker_site drop column moderatorrole;

alter table qawebsite_module_setting rename to qatracker_site_setting;
alter table qawebsite_module_setting_id_seq rename to qatracker_site_setting_id_seq;
alter table qatracker_site_setting alter column siteid set not null;
alter table qatracker_site_setting drop column module;

alter table qatracker_product drop column icon;
alter table qatracker_product add column familyid integer;

alter table qatracker_milestone add column notify smallint not null default 1;

alter table qatracker_result add column hardware varchar;

alter table qatracker_build add column note varchar;
