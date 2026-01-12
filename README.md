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
LiveAction();           // Purge live action table
EventsSmall();          // Purge events small table	
PurgeSentDocuments();   // Purge Sent Documents table
ExpiredSessionsDelete();// Delete expired sessions
BrandsPerCategoryRebuild();// Rebuild brands per category
UsersOnline();          // Populate users online count table             
ManagePricelist();      // Load Excel price list        
ManageTranscode();      // Transcode product codes to match with the codes on the PDFs
ExpiredNotes();         // Send email notification for notes on expired documents
Subscriptions();        // Cash-in expired subscriptions
Trials();               // Suspend users in expired trials
CheckPhpFpm();          // Check PHP-FPM Pinaxo Blog is not blocked
CheckCert();            // Check www.pinaxo.com cert is not expiring soon
AutoExpire();           // Set EXPIRE date of documents with very old RELEASE date and not read
AutoUncache();          // Set documents to not to be cached when they are very old and not read





