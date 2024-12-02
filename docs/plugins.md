FileDownload contains two deactivated plugins, that have to be renamed and
activated afterward (otherwise they are deactivated and reset to the original
code after each update of FileDownloadR).

- **FileDownloadEmail** sends an email each time when a file is downloaded.
- **FileDownloadFormSave** saves a form submit (FormIt + FormSave) each time when a file is downloaded.

If you want to create your own plugins, the following plugin events are
available after the installation of FileDownloadR:

| Event                            | Properties                                |
|----------------------------------|-------------------------------------------|
| OnFileDownloadLoad               | -                                         |
| OnFileDownloadBeforeDirOpen      | dirPath                                   |
| OnFileDownloadAfterDirOpen       | dirPath, contents                         |
| OnFileDownloadBeforeFileDownload | hash, ctx, filePath, mediaSourceId, count |
| OnFileDownloadAfterFileDownload  | hash, ctx, filePath, mediaSourceId, count |
| OnFileDownloadBeforeFileUpload   | filePath, fileName                        |
| OnFileDownloadAfterFileUpload    | filePath, fileName, hash, resourceId      |
| OnFileDownloadBeforeFileDelete   | hash, ctx, filePath, mediaSourceId, count |
| OnFileDownloadAfterFileDelete    | hash, ctx, filePath, mediaSourceId, count |
