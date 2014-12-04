#!/bin/sh
# Make sure we have a clean empty DB to work with
sudo -u postgres dropdb qatracker1 2> /dev/null > /dev/null
sudo -u postgres createdb qatracker1 -O qatracker

# Load a dump of a production database
cat /root/dump.sql.gz | gzip -d | psql -q -h localhost qatracker1 qatracker

# Run the pre-migration queries to convert the schema
cat MIGRATION.pre.sql | psql -q -h localhost qatracker1 qatracker

# Export only the tables we are interested in
pg_dump -h localhost -U qatracker -a --column-inserts -t users -t qatracker_bug -t qatracker_build -t qatracker_launchpad_bug -t qatracker_milestone -t qatracker_product -t qatracker_result -t qatracker_site -t qatracker_site_setting -t qatracker_testcase -t qatracker_user_subscription qatracker1 > newdb.sql

# Load the dump in the target
cat MIGRATION.pre-load.sql | psql -q -h localhost qatracker qatracker
cat newdb.sql | psql -q -h localhost qatracker qatracker

# Run the post-migration queries to add some needed data
cat MIGRATION.post.sql | psql -q -h localhost qatracker qatracker

# cleanup
rm -f newdb.sql
sudo -u postgres dropdb qatracker1 2> /dev/null > /dev/null
