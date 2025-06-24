---
layout: post
title: GCP CloudSQL data export and long-term archiving
excerpt_separator: <!--more-->
---

Example solution for long-term reliable data extraction storage.

<!--more-->

### Introduction 

Use-case: transaction ledger, social network profiles, analytical data, time-series datastore, messaging application.
After some time, the data becomes stale/immutable and it makes sense to export unnecessary data out of SQL store
* Reducing costs
* Increasing performance

Modern managed SQL hosting like AWS and GCP provide storage auto scaling capability, but do not allow to reduce already allocated storage.
It makes sense for data intensive applications to think about long-term capacity costs and do the export/archival solution.

### Requirements

- Long-term reliable storage for SQL data
- Reduced costs for very-long term large objects
- Low maintenance and operation costs
- Ease of use

#### Example solution

- Platform is GCP [cloud.google.com](https://cloud.google.com/).
- Data source is SQL instance of [Cloud SQL for PostgreSQL, MySQL und SQL Server](https://cloud.google.com/sql)
- Cold store is GCP CloudStorage - [Bucket/ObjectStorage](https://cloud.google.com/storage)
- Storage format: .csv, .sql, csv.gz or sql.gz file per export [MySQL dump](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)

#### [Export to CSV](https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/operations#ExportContext)

| **Pros**  | **Cons**  | 
|---|---|
| 	✅Easy to access &nbsp;| ⚠ Does not export NULL and newlines natively, exported as "N", adds trailing quote mark |
| 	✅Export partial data with `WHERE` &nbsp;| ⚠ Does not contain tables metadata  |
|  |  |

#### [Export to SQL](https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/operations#ExportContext)

| **Pros**  | **Cons**  | 
|---|---|
| 	✅Easy to verify and replay | ⚠ Increased data size due to metadata  |
| 	✅Metadata and table structure is saved | ⚠ Unable to customize MysqlDump options  |
| 	✅Universal sql dump storage format | ⚠ Whole table(s) dumps only (no WHERE filtering) |
| 	✅Allows to export schema only | ⚠ DDL breaks consistency: ALTER/CREAT/DROP/RENAME/TRUNCATE statements on other thread|
| 	✅Mysqldump with --single-transaction | ⚠ Slow export speed of about 10GB-gz-dumpfile/1h  |
| 	✅Mysqldump with --hex-blob |  |
| 	✅Mysqldump with [REPEATABLE READ](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html) |  |
| 	✅Does not contain CREATE DATABASE |  |
|  |  |

### Cloud SQL Export
[Best practices](https://cloud.google.com/sql/docs/mysql/import-export) for importing and exporting data.

![GCP CloudSQL export UI console](/images/cloudsql-export/gcp_cloudsql_export1.png)

Ways to export data
- Google Cloud console: **NO** option to export a *single table* (per 01.2023)
- official [REST api](https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/instances/export)
- official SDK/library in [go](https://github.com/googleapis/google-api-go-client), [Node.js](https://github.com/googleapis/google-api-nodejs-client), [PHP](https://github.com/googleapis/google-api-php-client-services) and other languages
- gcloud sql export sql [gcloud console utility](https://cloud.google.com/sdk/gcloud/reference/sql/export/sql) (utilizes REST api)

#### IMPORTANT facts to consider
- MySQL dump might read-lock NON-InnoDB tables
- Only one export running at a time, [operation](https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/operations) is asynchronous
- Performance depends on the source/target geolocation and instance performance/capacity
- Export operation will block/conflict with: backup, import, failover operations. Ensure export is not long-running.
- It is possible to run export against read-replica to reduce performance impact
- Export to SQL uses MySQL dump utility and will create the usual performance-hit, up to 85% of single core CPU.
- It is **NOT** possible to stop/cancel the started export operation
- Recommended to use same [SQL Mode](https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html) for export and import
- GCP CloudStorage - Bucket single file storage size limit is 5TB
- Objects/files in GCP Bucket can be marked **immutable** with using "rentetion" period setting
- CloudSQL instance storage can-not be downscaled, even if SQL server itself reduces database size footprint

### Terraform Example

    terraform {
      backend "gcs" {}
    }
    
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs
      provider "google" {
      ...
    }
    
    # Source SQL instance for export
    # @see https://cloud.google.com/sql/docs/mysql/import-export/import-export-sql
    # @see https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/instances/export
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs/resources/sql_database_instance
    resource "google_sql_database_instance" "database-instance-cloudsql-bigdata" {
      ...
    }
    
    # Target Bucket destination for export
    # @see https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/instances/export
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs/resources/storage_bucket
    resource "google_storage_bucket" "storage-bucket-bigdata" {
      name           = "bigdata"
      requester_pays = false
    }
    
    # Grant CloudSQL instance service-account permissions to create objects in Bucket
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs/resources/storage_bucket_iam
    resource "google_storage_bucket_iam_member" "iam-member-storage-bucket-bigdata" {
      depends_on = [
        google_sql_database_instance.database-instance-cloudsql-bigdata,
        google_storage_bucket.storage-bucket-bigdata
      ]
    
      bucket = google_storage_bucket.storage-bucket-bigdata.name
      member = "serviceAccount:${google_sql_database_instance.database-instance-cloudsql-bigdata.service_account_email_address}"
      role   = "roles/storage.objectCreator"
    }
    
    # Service account for end-user API client/SDK to call instances.export() REST API
    # @see https://cloud.google.com/docs/authentication/provide-credentials-adc
    # @see https://cloud.google.com/sql/docs/mysql/admin-api/rest/v1beta4/instances/export
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs/resources/google_service_account
    resource "google_service_account" "service-account-api-client-database-instance-cloudsql-bigdata" {
      account_id = "my-account-for-cloudsql-export"
      display_name = "CloudSQL bigdata exporter"
      description = "Account needed to execute export API commands against CloudSQL instance"
    }
    
    # Permissions for end-user service account to execute cloudsql.instances.export()
    # @see https://cloud.google.com/sql/docs/mysql/iam-roles
    # @see https://registry.terraform.io/providers/hashicorp/google/latest/docs/resources/google_project_iam
    resource "google_project_iam_member" "iam-member-database-instance-cloudsql-bigdata" {
      role = "roles/cloudsql.viewer"
      member = "serviceAccount:${google_service_account.service-account-api-client-database-instance-cloudsql-bigdata.email}"
    }

![GCP SQLInstance export](/images/cloudsql-export/gcloud-export-sql-2023.png)

### Summary

Using example solution it is possible to automate the export of "cold" data out of SQL instance.
 
### References

* [Google Application Default Credentials](https://cloud.google.com/docs/authentication/provide-credentials-adc)
* [GCP Cloud SQL export/import](https://cloud.google.com/sql/docs/mysql/import-export/import-export-sql)
* [MySQL partition exchange](https://dev.mysql.com/doc/refman/5.7/en/partitioning-management-exchange.html)
* [How to Automate Google Cloud SQL Backups Using Google Cloud Scheduler, Cloud Functions, Pub/Sub, Cloud Storage, and Cloud IAM](https://betterprogramming.pub/how-to-automate-google-cloud-sql-backups-2de6d3cc7d01)
* [Scheduling Cloud SQL exports using Cloud Functions and Cloud Scheduler](https://cloud.google.com/blog/topics/developers-practitioners/scheduling-cloud-sql-exports-using-cloud-functions-and-cloud-scheduler)
