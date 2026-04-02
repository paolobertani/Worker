## Pinaxo Worker

&nbsp;

### Usage:

Heavy duty tasks: `php worker.php -hd`

```
RunDocumentAutolocked();
RunDocumentWithCommand();
RunDocumentToFixMD5();
RunDocumentToCacheV2();
RunDocumentToCover();
RunDocumentToColor();
RunDocumentToQR();
RunDocumentToMeta();
RunDocumentToUncacheV2();
RunDocumentToRemove();
RunSentDocumentToPdfff();
RunDocumentToIdrolabTag( true );  // apply tags
RunDocumentToIdrolabTag( false ); // remove tags
StatsSearchesBuild();
UpdateQrCountTablePage();
```

&nbsp;

Tasks: `php worker.php`

```
DeleteBotEvents();      // Delete bot generated records from events table
BackupDatabases();      // Backup databases
LogRotate();            // Rotate log
IdrolabDoStats();       // Generate Idrolab Stats
EventsSmall();          // Purge events small table	
PurgeSentDocuments();   // Purge Sent Documents table
ExpiredCookiesDelete();// Delete cookies older than 12 months
TrimUsage();           // Trim usage table keeping current year and 1 past year
TrimUsagePerDocument();// Trim usage_per_document table keeping current year and 3 past years
TrimUsagePerUser();    // Trim usage_per_user table keeping current year and 2 past years
TrimSearchesPerBrand();// Trim searches_per_brand table keeping current year and 3 past years
BrandsPerCategoryRebuild();// Rebuild brands per category
ManagePricelist();      // Load Excel price list        
ManageTranscode();      // Transcode product codes to match with the codes on the PDFs
ExpiredNotes();         // Send email notification for notes on expired documents
Subscriptions();        // Cash-in expired subscriptions
Trials();               // Suspend users in expired trials
CheckPhpFpm();          // Check PHP-FPM Pinaxo Blog is not blocked
CheckCert();            // Check www.pinaxo.com cert is not expiring soon
AutoExpire();           // Set EXPIRE date of documents with very old RELEASE date and not read
AutoUncache();          // Set documents to not to be cached when they are very old and not read


