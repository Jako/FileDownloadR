You can use the following example to submit a form using AJAX and evaluate the
result of the submission. For example, if the result is true, you can call the
download connector of FileDownloadR to start the download.

The example is quite old and uses jQuery Tools for the overlay. It uses a
connector in `assets/components/yourpackage/connector.php` when the form is
submitted to download a file and to activate all other download links. 

The connector has to be added on your own. It has to return a JSON with
`"success": true` and `"link": "hash"` for the file download.

Maybe someone can prepare an example for this connector and a plain javascript
version, that replaces this solution.

You need to create the following chunks and call `[[$DownloadForm]]` in the page
content.

### DownloadForm

```html
[[!FileDownload?
&getDir=`assets/downloads`
&tplFile=`tplDownloadForm`
&downloadByOther=`1`
]]
<div class="form_overlay" id="formLink">
<h2>Download Form</h2>
<form action="[[~[[*id]]]]" method="post" class="form" id="downloaderForm">
    <input type="hidden" name="nospam:blank" value="">
    <input type="hidden" name="link" value="">
    <label for="name">Name:</label>
    <input type="text" name="name" id="name" value="" required>
    <br>
    <label for="email">Email:</label>
    <input type="email" name="email" id="email" value="" required>
    <br>
    <label for="phone">Phone:</label>
    <input type="number" name="phone" id="phone" value="" required>
    <br>
    <label for="country">Country:</label>
    <input type="text" name="country" id="country" value="" required>
    <br>
    <div class="form-buttons">
        <button type="submit">Send Contact Inquiry</button>
        <button type="reset">Reset</button>
    </div>
</form>
</div>
[[$tplDownloadScript:htmlToBottom]]
```

### tplDownloadForm

```html
<tr[[+fd.class]]>
    <td style="width:16px;"><img src="[[+fd.image]]" alt="[[+fd.image]]"></td>
    <td>
        <a href="javascript:void(0);" rel="#formLink" id="[[+fd.hash]]">
            [[+fd.filename]]
        </a>
        <small>([[+fd.count]] downloads)</small>
    </td>
    <td>[[+fd.sizeText]]</td>
    <td>[[+fd.date]]</td>
</tr>
[[-- description row if the &chkDesc=`chunkName` is provided --]]
[[+fd.description:notempty=`<tr>
    <td></td>
    <td colspan="3">[[+fd.description]]</td>
</tr>`:default=``]]
```

### tplDownloadScript

```html

<script>
    $(function () {
        var connector = 'assets/components/yourpackage/connector.php?';
        createForm();

        function createForm() {
            var fileLink = $('.fd-file a[rel="#formLink"]');
            fileLink.each(function (i) {
                var self = $(this);
                self.overlay({
                    effect: 'apple',
                    onLoad: function () {
                        var id = self.attr('id');
                        $('#downloaderForm input[name="link"]').val(id);
                    },
                    onBeforeClose: function () {
                        $('.error').hide();
                        $('#downloaderForm input').each(function () {
                            $(this).removeClass('invalid');
                        });
                        $('#downloaderForm input[name="link"]').val('');
                        clearForm($('#downloaderForm'));
                    }
                });
            });
            $('#downloaderForm').validator().submit(function (e) {
                var form = $(this);
                if (!e.isDefaultPrevented()) {
                    $.post(connector + 'assets/components/yourpackage/connector.php?action=web/form/add&ctx=web&' + form.serialize(), function (data) {
                        if (data && data.success === true) {
                            clearForm(form);
                            fileLink.each(function () {
                                $(this).overlay().close();
                            });
                            fileDownload('assets/components/filedownloadr/connector.php?action=web/file/get&ctx=web&link=' + data.link);
                            fileLink.each(function () {
                                var self = $(this);
                                self.off();
                                var link = self.attr('id');
                                self.attr('onclick', 'fileDownload("assets/components/filedownloadr/connector.php?action=web/file/get&ctx=web&link=' + link + '")');
                            });
                        } else {
                            form.data('validator').invalidate(data);
                        }
                    }, 'json');
                    e.preventDefault();
                }
            });
        }

        function clearForm(form) {
            form.find(':input').each(function () {
                switch (this.type) {
                    case 'password':
                    case 'select-multiple':
                    case 'select-one':
                    case 'text':
                    case 'email':
                    case 'number':
                    case 'textarea':
                        $(this).val('');
                        break;
                    case 'checkbox':
                    case 'radio':
                        this.checked = false;
                }
            });
        }

        function fileDownload(link) {
            $('<iframe/>', {
                src: link
            }).hide().appendTo($('body'));
        }
    });
</script>
```
