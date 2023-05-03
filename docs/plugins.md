FileDownload contains two deactivated plugins, that have to be renamed and
activated afterward (otherwise they are deactivated after each update of
FileDownloadR).

- **FileDownloadEmail** sends an email each time when a file is downloaded.
- **FileDownloadFormSave** saves a form submit (FormIt + FormSave) each time when a file is downloaded.

The following plugin events are available after the installation of
FileDownloadR:

| Event                            | Properties                                |
|----------------------------------|-------------------------------------------|
| OnFileDownloadLoad               | -                                         |
| OnFileDownloadBeforeDirOpen      | dirPath                                   |
| OnFileDownloadAfterDirOpen       | dirPath, contents                         |
| OnFileDownloadBeforeFileDownload | hash, ctx, filePath, mediaSourceId, count |
| OnFileDownloadAfterFileDownload  | hash, ctx, filePath, mediaSourceId, count |
