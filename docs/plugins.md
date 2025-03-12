FileDownload contains two deactivated plugins, that have to be renamed and
activated afterward (otherwise they are deactivated and reset to the original
code after each update of FileDownloadR).

- **FileDownloadEmail** sends an email each time when a file is downloaded.
- **FileDownloadFormSave** saves a form submit (FormIt + FormSave) each time when a file is downloaded.

If you want to create your own plugins, the following plugin events are
available after the installation of FileDownloadR:

| Event                            | Properties                                                      |
|----------------------------------|-----------------------------------------------------------------|
| OnFileDownloadLoad               | -                                                               |
| OnFileDownloadBeforeDirOpen      | dirPath                                                         |
| OnFileDownloadAfterDirOpen       | dirPath, contents                                               |
| OnFileDownloadBeforeFileDownload | hash, ctx, mediaSourceId, filePath, extended, count, count_user |
| OnFileDownloadAfterFileDownload  | hash, ctx, mediaSourceId, filePath, extended, count, count_user |
| OnFileDownloadBeforeFileUpload   | filePath, fileName, extended                                    |
| OnFileDownloadAfterFileUpload    | filePath, fileName, extended, hash, resourceId                  |
| OnFileDownloadBeforeFileDelete   | hash, ctx, mediaSourceId, filePath, extended, count, count_user |
| OnFileDownloadAfterFileDelete    | hash, ctx, mediaSourceId, filePath, extended, count, count_user |
