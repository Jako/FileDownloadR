FileDownload contains two deactivated plugins, that have to be renamed and
activated afterward (otherwise they are deactivated and reset to the original
code after each update of FileDownloadR).

- **FileDownloadEmail** sends an email each time when a file is downloaded.
- **FileDownloadFormSave** saves a form submit (FormIt + FormSave) each time when a file is downloaded.

If you want to create your own plugins, the following plugin events are
available after the installation of FileDownloadR:

| Event                            | Properties                                                                 |
|----------------------------------|----------------------------------------------------------------------------|
| OnFileDownloadLoad               | -                                                                          |
| OnFileDownloadBeforeDirOpen      | dirPath                                                                    |
| OnFileDownloadAfterDirOpen       | dirPath, contents                                                          |
| OnFileDownloadBeforeFileDownload | hash, fdPath, ctx, mediaSourceId, filePath, extended, count, count_user    |
| OnFileDownloadAfterFileDownload  | hash, fdPath, ctx, mediaSourceId, filePath, extended, count, count_user    |
| OnFileDownloadBeforeFileUpload   | mediaSourceId, filePath, fileName, extended, resourceId                    |
| OnFileDownloadAfterFileUpload    | hash, fdPath, ctx, mediaSourceId, filePath, fileName, extended, resourceId |
| OnFileDownloadBeforeFileDelete   | hash, fdPath, ctx, mediaSourceId, filePath, extended, count, count_user    |
| OnFileDownloadAfterFileDelete    | hash, fdPath, ctx, mediaSourceId, filePath, extended, count, count_user    |

The properties contain the following values:

| Property      | Value                                                                                                                                  |
|---------------|----------------------------------------------------------------------------------------------------------------------------------------|
| dirPath       | The dirctory path                                                                                                                      |
| contents      | Array of files in the dirctory                                                                                                         |
| hash          | The unique hash, that identifies the database entry in the fd_path table                                                               |
| fdPath        | The database entry in the fd_path table                                                                                                |
| ctx           | The MODX context, the file was uploaded with                                                                                           |
| mediaSourceId | The MODX media source, the file was uploaded to                                                                                        |
| filePath      | The file path of the uploaded file (does not contain the filename in OnFileDownloadBeforeFileUpload and OnFileDownloadAfterFileUpload) |
| fileName      | The file name of the uploaded file                                                                                                     |
| extended      | Array of extended fields                                                                                                               |
| count         | The download count of the file                                                                                                         |
| count_user    | The download count of the file by the current user                                                                                     |
