<?php
declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_module']['fields']['krabo_login_guest_jumpTo'] = $GLOBALS['TL_DCA']['tl_module']['fields']['reg_jumpTo'];
$GLOBALS['TL_DCA']['tl_module']['fields']['krabo_login_guest_jumpTo']['label'] = &$GLOBALS['TL_LANG']['tl_module']['krabo_login_guest_jumpTo'];

$GLOBALS['TL_DCA']['tl_module']['fields']['krabo_login_trans_key'] = $GLOBALS['TL_DCA']['tl_module']['fields']['cssID'];
$GLOBALS['TL_DCA']['tl_module']['fields']['krabo_login_trans_key']['label'] = &$GLOBALS['TL_LANG']['tl_module']['krabo_login_trans_key'];
$GLOBALS['TL_DCA']['tl_module']['fields']['krabo_login_trans_key']['eval']['multiple'] = false;

$GLOBALS['TL_DCA']['tl_module']['fields']['nc_passwordless_notification'] = $GLOBALS['TL_DCA']['tl_module']['fields']['nc_notification'];
$GLOBALS['TL_DCA']['tl_module']['fields']['nc_passwordless_notification']['label'] = &$GLOBALS['TL_LANG']['tl_module']['nc_passwordless_notification'];

$GLOBALS['TL_DCA']['tl_module']['fields']['nc_password_reset_notification'] = $GLOBALS['TL_DCA']['tl_module']['fields']['nc_notification'];
$GLOBALS['TL_DCA']['tl_module']['fields']['nc_password_reset_notification']['label'] = &$GLOBALS['TL_LANG']['tl_module']['nc_password_reset_notification'];

$GLOBALS['TL_DCA']['tl_module']['palettes']['krabo_login'] = '{title_legend},name,type;{config_legend},krabo_login_trans_key;editable,newsletters,nc_activation_notification,nc_password_reset_notification,nc_passwordless_notification;{account_legend},reg_groups,reg_assignDir;{redirect_legend},jumpTo,reg_jumpTo,krabo_login_guest_jumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';