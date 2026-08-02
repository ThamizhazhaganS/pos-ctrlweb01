<?php
/**
 * @var array $config
 */
?>

<?= form_open('config/saveMessage/', ['id' => 'message_config_form', 'enctype' => 'multipart/form-data', 'class' => 'form-horizontal']) ?>
    <div id="config_wrapper">
        <fieldset id="config_info">

            <div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
            <ul id="message_error_message_box" class="error_message_box"></ul>

            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.msg_uid'), 'msg_uid', ['class' => 'control-label col-xs-2 required']) ?>
                <div class="col-xs-4">
                    <div class="input-group">
                        <span class="input-group-addon input-sm">
                            <span class="glyphicon glyphicon-user"></span>
                        </span>
                        <?= form_input([
                            'name'  => 'msg_uid',
                            'id'    => 'msg_uid',
                            'class' => 'form-control input-sm required',
                            'value' => $config['msg_uid']
                        ]) ?>
                    </div>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.msg_pwd'), 'msg_pwd', ['class' => 'control-label col-xs-2 required']) ?>
                <div class="col-xs-4">
                    <div class="input-group">
                        <span class="input-group-addon input-sm">
                            <span class="glyphicon glyphicon-lock"></span>
                        </span>
                        <?= form_password([
                            'name'  => 'msg_pwd',
                            'id'    => 'msg_pwd',
                            'class' => 'form-control input-sm required',
                            'value' => $config['msg_pwd']
                        ]) ?>
                    </div>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.msg_src'), 'msg_src', ['class' => 'control-label col-xs-2 required']) ?>
                <div class="col-xs-4">
                    <div class="input-group">
                        <span class="input-group-addon input-sm">
                            <span class="glyphicon glyphicon-bullhorn"></span>
                        </span>
                        <?= form_input([
                            'name'  => 'msg_src',
                            'id'    => 'msg_src',
                            'class' => 'form-control input-sm required',
                            'value' => $config['msg_src'] == null ? $config['company'] : $config['msg_src']
                        ]) ?>
                    </div>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.msg_msg'), 'msg_msg', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_textarea([
                        'name'        => 'msg_msg',
                        'id'          => 'msg_msg',
                        'class'       => 'form-control input-sm',
                        'value'       => $config['msg_msg'],
                        'placeholder' => lang('Config.msg_msg_placeholder')
                    ]) ?>
                </div>
            </div>
            <div class="form-group form-group-sm">
                <?= form_label('WhatsApp API Provider', 'whatsapp_api_provider', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_dropdown('whatsapp_api_provider', [
                        'twilio' => 'Twilio',
                        'meta' => 'Meta Developer API'
                    ], $config['whatsapp_api_provider'] ?? 'twilio', ['class' => 'form-control input-sm', 'id' => 'whatsapp_api_provider']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label('WhatsApp Receipt Mode', 'whatsapp_receipt_mode', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_dropdown('whatsapp_receipt_mode', [
                        'manual' => 'Manual (Checkbox on Register)',
                        'automatic' => 'Automatic (Sends Instantly)'
                    ], $config['whatsapp_receipt_mode'] ?? 'manual', ['class' => 'form-control input-sm']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm twilio-fields">
                <?= form_label('Twilio Account SID', 'twilio_account_sid', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input([
                        'name'  => 'twilio_account_sid',
                        'id'    => 'twilio_account_sid',
                        'class' => 'form-control input-sm',
                        'value' => $config['twilio_account_sid'] ?? ''
                    ]) ?>
                </div>
            </div>

            <div class="form-group form-group-sm twilio-fields">
                <?= form_label('Twilio Auth Token', 'twilio_auth_token', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input([
                        'name'  => 'twilio_auth_token',
                        'id'    => 'twilio_auth_token',
                        'class' => 'form-control input-sm',
                        'value' => $config['twilio_auth_token'] ?? ''
                    ]) ?>
                </div>
            </div>

            <div class="form-group form-group-sm twilio-fields">
                <?= form_label('Twilio WhatsApp Number', 'twilio_whatsapp_number', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input([
                        'name'  => 'twilio_whatsapp_number',
                        'id'    => 'twilio_whatsapp_number',
                        'class' => 'form-control input-sm',
                        'value' => $config['twilio_whatsapp_number'] ?? ''
                    ]) ?>
                </div>
            </div>

            <div class="form-group form-group-sm meta-fields" style="display: none;">
                <?= form_label('Meta Access Token', 'meta_access_token', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input(['name' => 'meta_access_token', 'id' => 'meta_access_token', 'class' => 'form-control input-sm', 'value' => $config['meta_access_token'] ?? '']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm meta-fields" style="display: none;">
                <?= form_label('Meta Phone Number ID', 'meta_phone_number_id', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input(['name' => 'meta_phone_number_id', 'id' => 'meta_phone_number_id', 'class' => 'form-control input-sm', 'value' => $config['meta_phone_number_id'] ?? '']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm meta-fields" style="display: none;">
                <?= form_label('Meta Receipt Template', 'meta_receipt_template', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input(['name' => 'meta_receipt_template', 'id' => 'meta_receipt_template', 'class' => 'form-control input-sm', 'value' => $config['meta_receipt_template'] ?? '']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm meta-fields" style="display: none;">
                <?= form_label('Meta Marketing Template', 'meta_marketing_template', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_input(['name' => 'meta_marketing_template', 'id' => 'meta_marketing_template', 'class' => 'form-control input-sm', 'value' => $config['meta_marketing_template'] ?? '']) ?>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label('WhatsApp Receipt Message', 'whatsapp_receipt_message', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_textarea([
                        'name'  => 'whatsapp_receipt_message',
                        'id'    => 'whatsapp_receipt_message',
                        'class' => 'form-control input-sm',
                        'value' => $config['whatsapp_receipt_message'] ?? 'Thank you for your purchase! Here is your receipt: ',
                        'rows'  => 3
                    ]) ?>
                </div>
            </div>
            <?= form_submit([
                'name'  => 'submit_message',
                'id'    => 'submit_message',
                'value' => lang('Common.submit'),
                'class' => 'btn btn-primary btn-sm pull-right'
            ]) ?>

        </fieldset>
    </div>
<?= form_close() ?>

<script type="text/javascript">
    // Validation and submit handling
    $(document).ready(function() {
        $('#message_config_form').validate($.extend(form_support.handler, {

            errorLabelContainer: "#message_error_message_box",

            rules: {
                // msg_uid: "required",
                // msg_pwd: "required",
                // msg_src: "required"
            },

            messages: {
                // msg_uid: "<?= lang('Config.msg_uid_required') ?>",
                // msg_pwd: "<?= lang('Config.msg_pwd_required') ?>",
                // msg_src: "<?= lang('Config.msg_src_required') ?>"
            }
        }));

        $('#whatsapp_api_provider').change(function() {
            if ($(this).val() === 'meta') {
                $('.twilio-fields').hide();
                $('.meta-fields').show();
            } else {
                $('.meta-fields').hide();
                $('.twilio-fields').show();
            }
        }).trigger('change');
    });
</script>
