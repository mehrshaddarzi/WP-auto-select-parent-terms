<form action="#" method="post" id="<?php echo $data['id']; ?>_form" enctype="multipart/form-data">

    <?php echo $data['html']; ?>

    <?php
    if ($data['require_excel_file']) {
        ?>
        <input type="file" id="<?php echo $data['id']; ?>_excel_file" name="<?php echo $data['id']; ?>_excel_file">
        <?php
    } else {
        ?>
        <p class="submit" style="margin-top: 15px;">
            <input type="submit" name="" id="<?php echo $data['id']; ?>_button_process"
                                 class="button" value="ارزیابی داده ها"><span class="spinner"></span>
        </p>
        <?php
    }
    ?>

    <div id="<?php echo $data['id']; ?>-excel-import-alert"></div>

</form>

<script>
    jQuery(document).ready(function ($) {

        <?php
        if ($data['require_excel_file']) {
        ?>

        // OnChange File
        $("#<?php echo $data['id']; ?>_excel_file").on("change", function (e) {
            var fileTag = $(this);
            var fileData = fileTag[0].files[0];
            var filePath = fileTag.val();

            // Notification Reset
            var excel_alert_div = $("#<?php echo $data['id']; ?>-excel-import-alert");
            excel_alert_div.html('لطفا کمی صبر کنید ..').show();

            // Check File Extension
            if ($.inArray(filePath.split('.').pop().toLowerCase(), ['xlsx', 'xls']) === -1) {
                fileTag.val('');
                excel_alert_div.html('خطا: فایل با پسوند اکسل انتخاب کنید').show();
                return false;
            }

            // Check File Size
            let mb = <?php echo $data['max_file_size']; ?>;
            let byte = mb * 1024 * 1024;
            if (fileData.size > byte || fileData.fileSize > byte) {
                fileTag.val('');
                excel_alert_div.html('خطا: حجم فایل نباید بیش تر از ' + parseInt(mb) + ' مگابایت باشد').show();
                return false;
            }

            // Create Form Data
            var formData = new FormData();
            formData.append('action', '<?php echo $data['action']; ?>');
            formData.append('excel', fileData);

            // Upload Excel File
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: formData,
                processData: false,
                contentType: false,
                success: function (data, textStatus, xhr) {
                    fileTag.val('');
                    $("#<?php echo $data['id']; ?>_excel_file").hide();
                    excel_alert_div.html(data.data.message).show();
                },
                error: function (xhr, status, error) {
                    fileTag.val('');
                    excel_alert_div.html('خطا: ' + xhr.responseJSON.data.message).show();
                }
            });
        });

        <?php
        } else {
        ?>

        // RunFirst
        $("#<?php echo $data['id']; ?>_button_process").on("click", function (e) {
            e.preventDefault();

            // Notification Reset
            var excel_alert_div = $("#<?php echo $data['id']; ?>-excel-import-alert");
            excel_alert_div.html('لطفا کمی صبر کنید ..').show();

            // Create Form Data
            var formData = new FormData(document.getElementById("<?php echo $data['id']; ?>_form"));
            formData.append('action', '<?php echo $data['action']; ?>');

            // Ajax Request
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: formData,
                processData: false,
                contentType: false,
                success: function (data, textStatus, xhr) {
                    excel_alert_div.html(data.data.message).show();
                },
                error: function (xhr, status, error) {
                    excel_alert_div.html('خطا: ' + xhr.responseJSON.data.message).show();
                }
            });
        });

        <?php
        }
        ?>

        // Update System
        $(document).on("click", "#<?php echo $data['id']; ?>-button-action", function (e) {
            e.preventDefault();

            // Hide Step 1
            $("[data-<?php echo $data['id']; ?>-step=1]").hide();
            $("[data-<?php echo $data['id']; ?>-step=2]").show();

            // New Ajax Request
            jQuery.ajax({
                url: ajaxurl,
                type: 'get',
                dataType: "json",
                tryCount: 0,
                retryLimit: 5,
                data: {
                    'action': '<?php echo $data['action']; ?>_run',
                    'number_all': jQuery("#<?php echo $data['id']; ?>_number_all").val(),
                    '_': Date.now()
                },
                success: function (data) {
                    if (data.process_status === "complete") {

                        // Completed Process
                        $("[data-<?php echo $data['id']; ?>-step=2]").hide();
                        $("[data-<?php echo $data['id']; ?>-step=3]").show();

                    } else {

                        // Get number Process
                        jQuery("span#<?php echo $data['id']; ?>_num_page_process").html(data.number_process);

                        // Get process Percentage
                        jQuery("progress#<?php echo $data['id']; ?>_html_progress").attr("value", data.percentage);

                        // Again request
                        var _this = this;
                        setTimeout(function () {
                            $.ajax(_this);
                        }, 4000);
                    }
                },
                error: function () {

                    this.tryCount++;
                    if (this.tryCount <= this.retryLimit) {
                        var productAjax = this;
                        setTimeout(function () {
                            $.ajax(productAjax);
                        }, 4000, productAjax);
                        return;
                    }

                    $("[data-<?php echo $data['id']; ?>-step=2]").html("<p>خطایی در اجرای عملیات رخ داده است لطفا مجدد تلاش کنید</p>");
                }
            });
        });
    });
</script>

<style>
    #<?php echo $data['id']; ?>-excel-import-alert {
        margin: 25px 5px;
        border: 1px solid #e3e3e3;
        padding: 15px;
        border-radius: 10px;
        display: none;
    }
</style>