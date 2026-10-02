<?= view('partial/header') ?>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <span class="glyphicon glyphicon-bullhorn">&nbsp;</span>WhatsApp & SMS Marketing Campaign
                </h3>
            </div>
            <div class="panel-body">
                <?= form_open_multipart('messages/send', ['id' => 'send_sms_form', 'class' => 'form-horizontal']) ?>
                    <fieldset>

                        <!-- Message Channel -->
                        <div class="form-group form-group-sm">
                            <label for="message_type" class="col-xs-3 control-label">Message Channel</label>
                            <div class="col-xs-9">
                                <select name="message_type" id="message_type" class="form-control input-sm">
                                    <option value="whatsapp" selected>🟢 WhatsApp (Meta Marketing Template)</option>
                                    <option value="sms">📱 SMS</option>
                                    <option value="email">✉️ Email</option>
                                </select>
                            </div>
                        </div>

                        <!-- WhatsApp Marketing Template Name -->
                        <div class="form-group form-group-sm" id="template_group">
                            <label for="template_name" class="col-xs-3 control-label">Marketing Template</label>
                            <div class="col-xs-9">
                                <input class="form-control input-sm" type="text" name="template_name" id="template_name" value="<?= esc($meta_marketing_template ?? 'taz_market') ?>" placeholder="e.g. taz_market">
                                <span class="help-block" style="margin-bottom: 0; font-size: 11px;">
                                    Meta template name registered in your WhatsApp Business Manager. 
                                    <a href="<?= site_url('config#system_tab') ?>" target="_blank">Edit default in Store Settings</a>
                                </span>
                            </div>
                        </div>

                        <!-- Recipient Selection Section -->
                        <div class="form-group form-group-sm">
                            <label for="customer_select" class="col-xs-3 control-label">Customer Recipients</label>
                            <div class="col-xs-9">

                                <!-- Quick search input (identical to Sales register) -->
                                <div class="input-group input-group-sm" style="margin-bottom: 8px;">
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
                                    <input type="text" id="quick_customer_search" class="form-control input-sm" placeholder="Search by name, phone, or company to add...">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-info btn-sm" id="btn_select_all_customers" title="Select all customers with phone numbers">
                                            <span class="glyphicon glyphicon-ok-sign"></span> Select All (<?= esc($customer_count ?? 0) ?>)
                                        </button>
                                        <button type="button" class="btn btn-default btn-sm" id="btn_deselect_all_customers" title="Clear selection">
                                            <span class="glyphicon glyphicon-remove-sign"></span> Deselect All
                                        </button>
                                    </span>
                                </div>

                                <!-- Full Multi-Select Customer Dropdown -->
                                <div style="margin-bottom: 8px;">
                                    <select id="customer_select" class="selectpicker show-tick form-control" multiple data-style="btn-default customer-select-btn" 
                                            data-width="100%"
                                            data-live-search="true" 
                                            data-live-search-placeholder="Filter customers..."
                                            data-actions-box="true" 
                                            data-select-all-text="Select All"
                                            data-deselect-all-text="Deselect All"
                                            data-selected-text-format="count > 1" 
                                            data-count-selected-text="{0} of {1} customers selected"
                                            data-size="8" 
                                            title="Click to view and choose customers...">
                                        <?php if (!empty($customer_list)): ?>
                                            <?php foreach ($customer_list as $c): ?>
                                                <?php 
                                                    $isSelected = in_array($c['person_id'], $selected_customer_ids ?? []);
                                                    $phoneDisp = $c['phone'] ? '[' . $c['phone'] . ']' : '[No Phone]';
                                                ?>
                                                <option 
                                                    value="<?= $c['person_id'] ?>" 
                                                    data-id="<?= $c['person_id'] ?>" 
                                                    data-name="<?= esc($c['name']) ?>" 
                                                    data-phone="<?= esc($c['phone']) ?>" 
                                                    data-email="<?= esc($c['email']) ?>"
                                                    data-subtext="<?= esc($phoneDisp) ?>"
                                                    <?= $isSelected ? 'selected' : '' ?>>
                                                    <?= esc($c['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Visual Box of Selected Customers (with 1-click unselect tag) -->
                                <div id="selected_recipients_container" style="padding: 10px; background-color: #f8f9fa; border: 1px solid #dcdcdc; border-radius: 4px;">
                                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 6px;">
                                        Sending to: <span id="selected_count_badge" class="badge" style="background-color: #337ab7;">0</span> unique recipients
                                        <small class="text-muted" style="font-weight: normal; margin-left: 8px;">(Click any ✖ to unselect and remove)</small>
                                    </div>
                                    <div id="selected_tags_list" style="max-height: 130px; overflow-y: auto; line-height: 24px;">
                                        <!-- Tags rendered dynamically -->
                                    </div>
                                </div>

                                <!-- Collapsible manual extra numbers -->
                                <div style="margin-top: 8px;">
                                    <a href="#extra_numbers_collapse" data-toggle="collapse" style="font-size: 12px; text-decoration: none;">
                                        <span class="glyphicon glyphicon-plus-sign"></span> + Add Extra / Custom Numbers Manually (Non-Customers)
                                    </a>
                                    <div id="extra_numbers_collapse" class="collapse" style="margin-top: 5px;">
                                        <input type="text" id="manual_extra_numbers" class="form-control input-sm" placeholder="Enter additional phone numbers separated by comma (e.g. 919876543210, 919876543211)">
                                    </div>
                                </div>

                                <!-- Hidden input that submits all final comma-separated recipients -->
                                <input type="hidden" name="phone" id="phone" value="">
                            </div>
                        </div>

                        <!-- Email Subject (only visible for Email channel) -->
                        <div class="form-group form-group-sm" id="subject_group" style="display: none;">
                            <label for="subject" class="col-xs-3 control-label">Subject</label>
                            <div class="col-xs-9">
                                <input class="form-control input-sm" type="text" name="subject" id="subject" placeholder="Subject line for email">
                            </div>
                        </div>

                        <!-- Message Body -->
                        <div class="form-group form-group-sm">
                            <label for="message" class="col-xs-3 control-label"><?= lang('Messages.message') ?></label>
                            <div class="col-xs-9">
                                <input type="text" class="form-control input-sm" id="message" name="message" placeholder="Leave blank to use customer's name (e.g. 'Hello {name}'), or enter custom offer text">
                                <span class="help-block" id="message_hint" style="margin-bottom: 0; font-size: 11px;">
                                    💡 <strong>Personalization tip:</strong> Leave blank to automatically send each customer's real name in the template, or use <code>{name}</code> in your offer text.
                                </span>
                            </div>
                        </div>

                        <!-- Media Attachment -->
                        <div class="form-group form-group-sm">
                            <label for="attachment" class="col-xs-3 control-label">Header Poster / Image</label>
                            <div class="col-xs-9">
                                <input type="file" name="attachment" id="attachment" class="form-control input-sm" accept="image/*,application/pdf">
                                <span class="help-block" style="margin-bottom: 0; font-size: 11px;">
                                    Attach an image (JPG/PNG). If left blank, POS will automatically use your store logo header.
                                </span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group form-group-sm">
                            <div class="col-xs-9 col-xs-offset-3">
                                <button type="submit" id="btn_submit_message" class="btn btn-primary btn-sm">
                                    <span class="glyphicon glyphicon-send">&nbsp;</span>Send Message Now
                                </button>
                                <span id="sending_spinner" style="display: none; margin-left: 10px; color: #337ab7;">
                                    <i class="glyphicon glyphicon-refresh glyphicon-refresh-animate"></i> Sending messages... please wait...
                                </span>
                            </div>
                        </div>

                    </fieldset>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<?= view('partial/footer') ?>

<script type="text/javascript">
    function syncRecipients() {
        var currentChannel = $('#message_type').val();
        var selectedOpts = $('#customer_select').find('option:selected');
        var contacts = [];
        var tagsHtml = '';

        selectedOpts.each(function() {
            var opt = $(this);
            var id = opt.data('id');
            var name = opt.data('name');
            var phone = (opt.data('phone') || '').toString().trim();
            var email = (opt.data('email') || '').toString().trim();

            var contactVal = (currentChannel === 'email') ? email : phone;

            if (contactVal) {
                if (contacts.indexOf(contactVal) === -1) {
                    contacts.push(contactVal);
                }
                tagsHtml += '<span class="label label-info recipient-pill" data-id="' + id + '" style="display: inline-block; margin: 2px 4px 2px 0; padding: 5px 8px; font-size: 12px; cursor: pointer;" title="Click to remove">' +
                    escHtml(name) + ' <small>(' + escHtml(contactVal) + ')</small> <span class="glyphicon glyphicon-remove" style="margin-left: 4px;"></span></span>';
            }
        });

        // Add manual extra numbers
        var manualVal = $('#manual_extra_numbers').val().trim();
        if (manualVal) {
            var extras = manualVal.split(',').map(function(item) { return item.trim(); }).filter(function(item) { return item.length > 0; });
            extras.forEach(function(extra) {
                if (contacts.indexOf(extra) === -1) {
                    contacts.push(extra);
                }
                tagsHtml += '<span class="label label-default recipient-pill-manual" style="display: inline-block; margin: 2px 4px 2px 0; padding: 5px 8px; font-size: 12px;">' +
                    escHtml(extra) + ' <small>(Manual)</small></span>';
            });
        }

        $('#phone').val(contacts.join(', '));
        $('#selected_count_badge').text(contacts.length);

        if (tagsHtml === '') {
            $('#selected_tags_list').html('<span class="text-muted" style="font-size: 12px;">No customers currently selected. Use search above or click "Select All".</span>');
        } else {
            $('#selected_tags_list').html(tagsHtml);
        }
    }

    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    $(document).ready(function() {
        // Initialize bootstrap-selectpicker
        $('.selectpicker').selectpicker();

        // Autocomplete quick search (identical to Sales register)
        $('#quick_customer_search').autocomplete({
            source: "<?= site_url('customers/suggest') ?>",
            minChars: 0,
            delay: 10,
            select: function(event, ui) {
                var personId = ui.item.value;
                var opt = $('#customer_select').find('option[data-id="' + personId + '"]');
                if (opt.length) {
                    opt.prop('selected', true);
                    $('#customer_select').selectpicker('refresh');
                    syncRecipients();
                    $.notify({ message: 'Added ' + ui.item.label + ' to recipients.' }, { type: 'success' });
                }
                $(this).val('');
                return false;
            }
        });

        // Remove recipient when clicking a pill tag
        $(document).on('click', '.recipient-pill', function() {
            var personId = $(this).data('id');
            var opt = $('#customer_select').find('option[data-id="' + personId + '"]');
            opt.prop('selected', false);
            $('#customer_select').selectpicker('refresh');
            syncRecipients();
        });

        // Change in dropdown selection
        $('#customer_select').on('changed.bs.select', function() {
            syncRecipients();
        });

        // Manual numbers input changed
        $('#manual_extra_numbers').on('input change', function() {
            syncRecipients();
        });

        // Select All Customers Button
        $('#btn_select_all_customers').click(function() {
            var currentChannel = $('#message_type').val();
            $('#customer_select option').each(function() {
                var phone = ($(this).data('phone') || '').toString().trim();
                var email = ($(this).data('email') || '').toString().trim();
                if (currentChannel === 'email') {
                    $(this).prop('selected', email.length > 0);
                } else {
                    $(this).prop('selected', phone.length > 0);
                }
            });
            $('#customer_select').selectpicker('refresh');
            syncRecipients();
            $.notify({ message: "Selected all available customers!" }, { type: 'info' });
        });

        // Deselect All Customers Button
        $('#btn_deselect_all_customers').click(function() {
            $('#customer_select option').prop('selected', false);
            $('#customer_select').selectpicker('refresh');
            syncRecipients();
        });

        // Message Channel switch
        $('#message_type').change(function() {
            var val = $(this).val();
            if (val === 'whatsapp') {
                $('#template_group').show();
                $('#subject_group').hide();
                $('#message_hint').html('💡 <strong>Personalization tip:</strong> Leave blank to automatically send each customer\'s real name in the template, or use <code>{name}</code> in your offer text.');
            } else if (val === 'email') {
                $('#template_group').hide();
                $('#subject_group').show();
                $('#message_hint').text('Email message body.');
            } else {
                $('#template_group').hide();
                $('#subject_group').hide();
                $('#message_hint').text('Standard text SMS message.');
            }

            // Update subtext on options to show either phone or email
            $('#customer_select option').each(function() {
                var phone = $(this).data('phone') || 'No Phone';
                var email = $(this).data('email') || 'No Email';
                if (val === 'email') {
                    $(this).data('subtext', '[' + email + ']');
                    $(this).attr('data-subtext', '[' + email + ']');
                } else {
                    $(this).data('subtext', '[' + phone + ']');
                    $(this).attr('data-subtext', '[' + phone + ']');
                }
            });
            $('#customer_select').selectpicker('refresh');
            syncRecipients();
        }).trigger('change');

        // Initial sync
        syncRecipients();

        // Form submit handling
        $('#send_sms_form').validate({
            submitHandler: function(form) {
                var phoneVal = $('#phone').val().trim();
                if (!phoneVal) {
                    $.notify({ message: 'Please select at least one customer recipient.' }, { type: 'warning' });
                    return false;
                }

                $('#btn_submit_message').prop('disabled', true);
                $('#sending_spinner').show();

                $(form).ajaxSubmit({
                    success: function(response) {
                        $('#btn_submit_message').prop('disabled', false);
                        $('#sending_spinner').hide();
                        $.notify({
                            message: response.message
                        }, {
                            type: response.success ? 'success' : 'danger',
                            delay: 6000
                        });
                    },
                    error: function(xhr) {
                        $('#btn_submit_message').prop('disabled', false);
                        $('#sending_spinner').hide();
                        var errMsg = 'An error occurred while sending messages.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errMsg = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                var parsed = JSON.parse(xhr.responseText);
                                if (parsed.message) errMsg = parsed.message;
                            } catch(e) {}
                        }
                        $.notify({
                            message: errMsg
                        }, {
                            type: 'danger',
                            delay: 8000
                        });
                    },
                    dataType: 'json'
                });
            }
        });
    });
</script>

<style>
.customer-select-btn {
    background-color: #ffffff !important;
    color: #1a202c !important;
    border: 2px solid #2b6cb0 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    padding: 10px 14px !important;
    height: auto !important;
    min-height: 44px !important;
    border-radius: 6px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}
.customer-select-btn:hover,
.customer-select-btn:focus,
.customer-select-btn:active {
    background-color: #f7fafc !important;
    border-color: #2c5282 !important;
    color: #1a202c !important;
}
.bootstrap-select > .btn.customer-select-btn .filter-option,
.bootstrap-select > .btn.customer-select-btn .filter-option-inner-inner {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #1a202c !important;
}
.bootstrap-select > .btn.customer-select-btn .bs-caret .caret {
    border-top: 6px solid #2b6cb0 !important;
    border-right: 5px solid transparent !important;
    border-left: 5px solid transparent !important;
}
.recipient-pill:hover {
    background-color: #d9534f !important;
    text-decoration: line-through;
}
.bootstrap-select .dropdown-menu {
    max-height: 280px !important;
}
.bootstrap-select .bs-actionsbox {
    padding: 6px 10px;
}
.bootstrap-select .bs-actionsbox .btn-group button {
    font-size: 11px;
}
.glyphicon-refresh-animate {
    animation: spin 1s infinite linear;
    -webkit-animation: spin2 1s infinite linear;
}
@-webkit-keyframes spin2 {
    from { -webkit-transform: rotate(0deg);}
    to { -webkit-transform: rotate(360deg);}
}
@keyframes spin {
    from { transform: scale(1) rotate(0deg);}
    to { transform: scale(1) rotate(360deg);}
}
</style>
