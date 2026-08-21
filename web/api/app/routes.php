<?php

declare(strict_types=1);

use Slim\App;
use DI\Container;
use tgui\Controllers\HomeController;
use tgui\Controllers\Auth\AuthController;
use tgui\Controllers\API\APIUsers\APIUsersCtrl;
use tgui\Controllers\API\APIUserGrps\APIUserGrpsCtrl;
use tgui\Controllers\APISettings\APISettingsCtrl;
use tgui\Controllers\APIHA\APIHACtrl;
use tgui\Controllers\Obj\ObjAddress\ObjAddress;
use tgui\Controllers\TAC\TACDevices\TACDevicesCtrl;
use tgui\Controllers\TAC\TACDeviceGrps\TACDeviceGrpsCtrl;
use tgui\Controllers\TAC\TACUsers\TACUsersCtrl;
use tgui\Controllers\TAC\TACUserGrps\TACUserGrpsCtrl;
use tgui\Controllers\TAC\TACACL\TACACLCtrl;
use tgui\Controllers\TAC\TACServices\TACServicesCtrl;
use tgui\Controllers\TAC\TACCMD\TACCMDCtrl;
use tgui\Controllers\TACConfig\TACConfigCtrl;
use tgui\Controllers\TACReports\TACReportsCtrl;
use tgui\Controllers\TACExport\TACExportCtrl;
use tgui\Controllers\TACImport\TACImportCtrl;
use tgui\Controllers\APIChecker\APICheckerCtrl;
use tgui\Controllers\APIBackup\APIBackupCtrl;
use tgui\Controllers\APIUpdate\APIUpdateCtrl;
use tgui\Controllers\MAVIS\MAVISLDAP\MAVISLDAPCtrl;
use tgui\Controllers\MAVIS\MAVISOTP\MAVISOTPCtrl;
use tgui\Controllers\MAVIS\MAVISSMS\MAVISSMSCtrl;
use tgui\Controllers\MAVIS\MAVISLocal\MAVISLocalCtrl;
use tgui\Controllers\APILogging\APILoggingCtrl;
use tgui\Controllers\APINotification\APINotificationCtrl;
use tgui\Controllers\APIDownload\APIDownloadCtrl;
use tgui\Controllers\ConfManager\ConfManager;
use tgui\Controllers\ConfManager\ConfModels;
use tgui\Controllers\ConfManager\ConfDevices;
use tgui\Controllers\ConfManager\ConfGroups;
use tgui\Controllers\ConfManager\ConfigCredentials;
use tgui\Controllers\ConfManager\ConfQueries;
use tgui\Controllers\APIDev\APIDevCtrl;

/**
 * @param App $app
 * @param Container $container
 */
function registerRoutes(App $app, Container $container): void
{
    // Home routes
    $app->get('/', fn($request, $response) => $container->get(HomeController::class)->getHome($request, $response));
    $app->post('/', fn($request, $response) => $container->get(HomeController::class)->postHome($request, $response));

    // Authentication Routes
    $app->get('/auth/signin/', fn($request, $response) => $container->get(AuthController::class)->getSignIn($request, $response));
    $app->post('/auth/signin/', fn($request, $response) => $container->get(AuthController::class)->postSignIn($request, $response));
    $app->post('/auth/signin/changePassword/', fn($request, $response) => $container->get(AuthController::class)->postChangePassword($request, $response));
    $app->get('/auth/signout/', fn($request, $response) => $container->get(AuthController::class)->getSignOut($request, $response));

    // User Routes
    $app->post('/user/datatables/', fn($request, $response) => $container->get(APIUsersCtrl::class)->postUserDatatables($request, $response));
    $app->get('/user/add/', fn($request, $response) => $container->get(APIUsersCtrl::class)->getUserAdd($request, $response));
    $app->post('/user/add/', fn($request, $response) => $container->get(APIUsersCtrl::class)->postUserAdd($request, $response));
    $app->get('/user/edit/', fn($request, $response) => $container->get(APIUsersCtrl::class)->getUserEdit($request, $response));
    $app->post('/user/edit/', fn($request, $response) => $container->get(APIUsersCtrl::class)->postUserEdit($request, $response));
    $app->get('/user/delete/', fn($request, $response) => $container->get(APIUsersCtrl::class)->getUserDelete($request, $response));
    $app->post('/user/delete/', fn($request, $response) => $container->get(APIUsersCtrl::class)->postUserDelete($request, $response));
    $app->get('/user/info/', fn($request, $response) => $container->get(APIUsersCtrl::class)->getUserInfo($request, $response));
    $app->get('/user/status/', fn($request, $response) => $container->get(APIUsersCtrl::class)->getUserStatus($request, $response));

    // User Group Routes
    $app->post('/user/group/datatables/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->postUserGroupDatatables($request, $response));
    $app->get('/user/group/add/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->getUserGroupAdd($request, $response));
    $app->post('/user/group/add/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->postUserGroupAdd($request, $response));
    $app->get('/user/group/edit/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->getUserGroupEdit($request, $response));
    $app->post('/user/group/edit/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->postUserGroupEdit($request, $response));
    $app->get('/user/group/delete/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->getUserGroupDelete($request, $response));
    $app->post('/user/group/delete/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->postUserGroupDelete($request, $response));
    $app->post('/user/group/rights/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->postUserGroupRightsList($request, $response));
    $app->get('/user/group/list/', fn($request, $response) => $container->get(APIUserGrpsCtrl::class)->getUserGroupList($request, $response));

    // API Settings Routes
    $app->get('/settings/pwpolicy/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getPasswdPolicy($request, $response));
    $app->post('/settings/pwpolicy/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postPasswdPolicy($request, $response));
    $app->get('/settings/smtp/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getSmtp($request, $response));
    $app->post('/settings/smtp/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postSmtp($request, $response));
    $app->post('/settings/smtp/test/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postSmtpTest($request, $response));
    $app->get('/settings/time/timezones/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getTimeTimezones($request, $response));
    $app->post('/settings/time/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postTimeSettings($request, $response));
    $app->get('/settings/time/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getTimeSettings($request, $response));
    $app->get('/settings/time/status/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getTimeStatus($request, $response));
    $app->get('/settings/network/interface/list/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getInterfaceList($request, $response));
    $app->get('/settings/network/interface/', fn($request, $response) => $container->get(APISettingsCtrl::class)->getInterfaceSettings($request, $response));
    $app->post('/settings/network/interface/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postInterfaceSettings($request, $response));
    $app->get('/settings/ha/', fn($request, $response) => $container->get(APIHACtrl::class)->getSettings($request, $response));
    $app->post('/settings/ha/', fn($request, $response) => $container->get(APIHACtrl::class)->postSettings($request, $response));
    $app->post('/settings/ha/status/', fn($request, $response) => $container->get(APIHACtrl::class)->getStatus($request, $response));
    $app->post('/settings/ha/slave/del/', fn($request, $response) => $container->get(APIHACtrl::class)->postSlaveDel($request, $response));
    $app->post('/settings/ha/slave/list/', fn($request, $response) => $container->get(APISettingsCtrl::class)->postHASlaveList($request, $response));

    // TACACS Address Routes
    $app->post('/obj/address/datatables/', fn($request, $response) => $container->get(ObjAddress::class)->postDatatables($request, $response));
    $app->post('/obj/address/add/', fn($request, $response) => $container->get(ObjAddress::class)->postAdd($request, $response));
    $app->get('/obj/address/edit/', fn($request, $response) => $container->get(ObjAddress::class)->getEdit($request, $response));
    $app->post('/obj/address/edit/', fn($request, $response) => $container->get(ObjAddress::class)->postEdit($request, $response));
    $app->post('/obj/address/delete/', fn($request, $response) => $container->get(ObjAddress::class)->postDel($request, $response));
    $app->post('/obj/address/csv/', fn($request, $response) => $container->get(ObjAddress::class)->postCsv($request, $response));
    $app->get('/obj/address/list/', fn($request, $response) => $container->get(ObjAddress::class)->getList($request, $response));
    $app->get('/obj/address/ref/', fn($request, $response) => $container->get(ObjAddress::class)->getRef($request, $response));

    // TACACS Device Routes
    $app->post('/tacacs/device/datatables/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->postDeviceDatatables($request, $response));
    $app->get('/tacacs/device/ping/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->getDevicePing($request, $response));
    $app->get('/tacacs/device/add/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->getDeviceAdd($request, $response));
    $app->post('/tacacs/device/add/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->postDeviceAdd($request, $response));
    $app->get('/tacacs/device/edit/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->getDeviceEdit($request, $response));
    $app->post('/tacacs/device/edit/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->postDeviceEdit($request, $response));
    $app->get('/tacacs/device/delete/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->getDeviceDelete($request, $response));
    $app->post('/tacacs/device/delete/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->postDeviceDelete($request, $response));
    $app->post('/tacacs/device/csv/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->postDeviceCsv($request, $response));
    $app->get('/tacacs/device/list/', fn($request, $response) => $container->get(TACDevicesCtrl::class)->getList($request, $response));

    // TACACS Device Group Routes
    $app->post('/tacacs/device/group/datatables/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->postDeviceGroupsDatatables($request, $response));
    $app->get('/tacacs/device/group/add/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->getDeviceGroupAdd($request, $response));
    $app->post('/tacacs/device/group/add/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->postDeviceGroupAdd($request, $response));
    $app->get('/tacacs/device/group/edit/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->getDeviceGroupEdit($request, $response));
    $app->post('/tacacs/device/group/edit/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->postDeviceGroupEdit($request, $response));
    $app->get('/tacacs/device/group/delete/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->getDeviceGroupDelete($request, $response));
    $app->post('/tacacs/device/group/delete/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->postDeviceGroupDelete($request, $response));
    $app->get('/tacacs/device/group/list/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->getDeviceGroupList($request, $response));
    $app->post('/tacacs/device/group/csv/', fn($request, $response) => $container->get(TACDeviceGrpsCtrl::class)->postDeviceGroupCsv($request, $response));

    // TACACS User Routes
    $app->post('/tacacs/user/datatables/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserDatatables($request, $response));
    $app->get('/tacacs/user/add/', fn($request, $response) => $container->get(TACUsersCtrl::class)->getUserAdd($request, $response));
    $app->post('/tacacs/user/add/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserAdd($request, $response));
    $app->get('/tacacs/user/edit/', fn($request, $response) => $container->get(TACUsersCtrl::class)->getUserEdit($request, $response));
    $app->post('/tacacs/user/edit/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserEdit($request, $response));
    $app->get('/tacacs/user/delete/', fn($request, $response) => $container->get(TACUsersCtrl::class)->getUserDelete($request, $response));
    $app->post('/tacacs/user/delete/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserDelete($request, $response));
    $app->post('/tacacs/user/change_passwd/change/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserPWChange($request, $response));
    $app->post('/tacacs/user/change_passwd/send/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postSendPasswd($request, $response));
    $app->post('/tacacs/user/csv/', fn($request, $response) => $container->get(TACUsersCtrl::class)->postUserCsv($request, $response));

    // TACACS User Group Routes
    $app->post('/tacacs/user/group/datatables/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->postUserGroupDatatables($request, $response));
    $app->get('/tacacs/user/group/add/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->getUserGroupAdd($request, $response));
    $app->post('/tacacs/user/group/add/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->postUserGroupAdd($request, $response));
    $app->get('/tacacs/user/group/edit/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->getUserGroupEdit($request, $response));
    $app->post('/tacacs/user/group/edit/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->postUserGroupEdit($request, $response));
    $app->get('/tacacs/user/group/delete/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->getUserGroupDelete($request, $response));
    $app->post('/tacacs/user/group/delete/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->postUserGroupDelete($request, $response));
    $app->get('/tacacs/user/group/list/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->getUserGroupList($request, $response));
    $app->get('/tacacs/user/group/ldap/list/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->getLDAPGroupList($request, $response));
    $app->post('/tacacs/user/group/csv/', fn($request, $response) => $container->get(TACUserGrpsCtrl::class)->postUserGroupCsv($request, $response));

    // TACACS ACL Routes
    $app->post('/tacacs/acl/datatables/', fn($request, $response) => $container->get(TACACLCtrl::class)->postACLDatatables($request, $response));
    $app->get('/tacacs/acl/add/', fn($request, $response) => $container->get(TACACLCtrl::class)->getACLAdd($request, $response));
    $app->post('/tacacs/acl/add/', fn($request, $response) => $container->get(TACACLCtrl::class)->postACLAdd($request, $response));
    $app->get('/tacacs/acl/edit/', fn($request, $response) => $container->get(TACACLCtrl::class)->getACLEdit($request, $response));
    $app->post('/tacacs/acl/edit/', fn($request, $response) => $container->get(TACACLCtrl::class)->postACLEdit($request, $response));
    $app->get('/tacacs/acl/delete/', fn($request, $response) => $container->get(TACACLCtrl::class)->getACLDelete($request, $response));
    $app->post('/tacacs/acl/delete/', fn($request, $response) => $container->get(TACACLCtrl::class)->postACLDelete($request, $response));
    $app->get('/tacacs/acl/list/', fn($request, $response) => $container->get(TACACLCtrl::class)->getAclList($request, $response));
    $app->get('/tacacs/acl/ref/', fn($request, $response) => $container->get(TACACLCtrl::class)->getAclRef($request, $response));
    $app->post('/tacacs/acl/csv/', fn($request, $response) => $container->get(TACACLCtrl::class)->postACLCsv($request, $response));

    // TACACS Service Routes
    $app->post('/tacacs/service/datatables/', fn($request, $response) => $container->get(TACServicesCtrl::class)->postServiceDatatables($request, $response));
    $app->get('/tacacs/service/add/', fn($request, $response) => $container->get(TACServicesCtrl::class)->getServiceAdd($request, $response));
    $app->post('/tacacs/service/add/', fn($request, $response) => $container->get(TACServicesCtrl::class)->postServiceAdd($request, $response));
    $app->get('/tacacs/service/edit/', fn($request, $response) => $container->get(TACServicesCtrl::class)->getServiceEdit($request, $response));
    $app->post('/tacacs/service/edit/', fn($request, $response) => $container->get(TACServicesCtrl::class)->postServiceEdit($request, $response));
    $app->get('/tacacs/service/delete/', fn($request, $response) => $container->get(TACServicesCtrl::class)->getServiceDelete($request, $response));
    $app->post('/tacacs/service/delete/', fn($request, $response) => $container->get(TACServicesCtrl::class)->postServiceDelete($request, $response));
    $app->get('/tacacs/service/list/', fn($request, $response) => $container->get(TACServicesCtrl::class)->getServiceList($request, $response));
    $app->get('/tacacs/service/ref/', fn($request, $response) => $container->get(TACServicesCtrl::class)->getServiceRef($request, $response));
    $app->post('/tacacs/service/csv/', fn($request, $response) => $container->get(TACServicesCtrl::class)->postServiceCsv($request, $response));

    // TACACS CMD Routes
    $app->post('/obj/cmd/datatables/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postDatatables($request, $response));
    $app->get('/obj/cmd/add/', fn($request, $response) => $container->get(TACCMDCtrl::class)->getAdd($request, $response));
    $app->post('/obj/cmd/add/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postAdd($request, $response));
    $app->get('/obj/cmd/edit/', fn($request, $response) => $container->get(TACCMDCtrl::class)->getEdit($request, $response));
    $app->post('/obj/cmd/edit/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postEdit($request, $response));
    $app->post('/obj/cmd/edit/type/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postEditType($request, $response));
    $app->get('/obj/cmd/delete/', fn($request, $response) => $container->get(TACCMDCtrl::class)->getDelete($request, $response));
    $app->post('/obj/cmd/delete/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postDelete($request, $response));
    $app->get('/obj/cmd/list/', fn($request, $response) => $container->get(TACCMDCtrl::class)->getList($request, $response));
    $app->get('/obj/cmd/list/junos/', fn($request, $response) => $container->get(TACCMDCtrl::class)->getListJunos($request, $response));
    $app->post('/obj/cmd/csv/', fn($request, $response) => $container->get(TACCMDCtrl::class)->postCsv($request, $response));

    // TACACS Configuration Generator Routes
    $app->get('/tacacs/config/apply/', fn($request, $response) => $container->get(TACConfigCtrl::class)->getConfigGenFile($request, $response));
    $app->post('/tacacs/config/apply/slave/', fn($request, $response) => $container->get(TACConfigCtrl::class)->postApplySlaveCfg($request, $response));
    $app->get('/tacacs/config/generate/', fn($request, $response) => $container->get(TACConfigCtrl::class)->getConfigGen($request, $response));
    $app->post('/tacacs/config/generate/', fn($request, $response) => $container->get(TACConfigCtrl::class)->postConfigGen($request, $response));
    $app->post('/tacacs/config/daemon/', fn($request, $response) => $container->get(TACConfigCtrl::class)->postDaemonConfig($request, $response));
    $app->get('/tacacs/config/global/edit/', fn($request, $response) => $container->get(TACConfigCtrl::class)->getEditConfigGlobal($request, $response));
    $app->post('/tacacs/config/global/edit/', fn($request, $response) => $container->get(TACConfigCtrl::class)->postEditConfigGlobal($request, $response));
    $app->post('/tacacs/config/part/', fn($request, $response) => $container->get(TACConfigCtrl::class)->postConfPart($request, $response));

    // TACACS Reports Routes
    $app->post('/tacacs/reports/accounting/datatables/', fn($request, $response) => $container->get(TACReportsCtrl::class)->postAccountingDatatables($request, $response));
    $app->post('/tacacs/reports/authentication/datatables/', fn($request, $response) => $container->get(TACReportsCtrl::class)->postAuthenticationDatatables($request, $response));
    $app->post('/tacacs/reports/authorization/datatables/', fn($request, $response) => $container->get(TACReportsCtrl::class)->postAuthorizationDatatables($request, $response));
    $app->get('/tacacs/reports/general/', fn($request, $response) => $container->get(TACReportsCtrl::class)->getGeneralReport($request, $response));
    $app->get('/tacacs/reports/daemon/status/', fn($request, $response) => $container->get(TACReportsCtrl::class)->getDaemonStatus($request, $response));
    $app->get('/tacacs/reports/top/access/', fn($request, $response) => $container->get(TACReportsCtrl::class)->getTopAccess($request, $response));
    $app->post('/tacacs/reports/tree/', fn($request, $response) => $container->get(TACReportsCtrl::class)->postFileTree($request, $response));
    $app->post('/tacacs/reports/delete/', fn($request, $response) => $container->get(TACReportsCtrl::class)->postLogDelete($request, $response));
    $app->get('/tacacs/widget/chart/auth/', fn($request, $response) => $container->get(TACReportsCtrl::class)->getAuthChartData($request, $response));

    // TACACS Export/Import Routes
    $app->get('/export/tacacs/', fn($request, $response) => $container->get(TACExportCtrl::class)->getExport($request, $response));
    $app->post('/import/upload/file/', fn($request, $response) => $container->get(TACImportCtrl::class)->postFile($request, $response));

    // API Checker Routes
    $app->get('/apicheck/database/', fn($request, $response) => $container->get(APICheckerCtrl::class)->getCheckDatabase($request, $response));
    $app->get('/apicheck/status/', fn($request, $response) => $container->get(APICheckerCtrl::class)->getApiStatus($request, $response));
    $app->get('/apicheck/time/', fn($request, $response) => $container->get(APICheckerCtrl::class)->getApiTime($request, $response));

    // Backup Routes
    $app->post('/backup/datatables/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupDatatables($request, $response));
    $app->post('/backup/delete/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupDelete($request, $response));
    $app->post('/backup/restore/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupRestore($request, $response));
    $app->get('/backup/download/', fn($request, $response) => $container->get(APIBackupCtrl::class)->getBackupDownload($request, $response));
    $app->post('/backup/upload/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupUpload($request, $response));
    $app->post('/backup/make/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupMake($request, $response));
    $app->get('/backup/settings/', fn($request, $response) => $container->get(APIBackupCtrl::class)->getBackupSettings($request, $response));
    $app->post('/backup/settings/', fn($request, $response) => $container->get(APIBackupCtrl::class)->postBackupSettings($request, $response));

    // Update Routes
    $app->get('/update/info/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->getInfo($request, $response));
    $app->post('/update/info/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postInfo($request, $response));
    $app->post('/update/info/slave/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postInfoSlave($request, $response));
    $app->post('/update/upgrade/slave/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postUpgradeSlave($request, $response));
    $app->post('/update/change/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postChange($request, $response));
    $app->post('/update/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postCheck($request, $response));
    $app->post('/update/upgrade/', fn($request, $response) => $container->get(APIUpdateCtrl::class)->postUpgrade($request, $response));

    // MAVIS Routes
    $app->get('/mavis/ldap/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->getLDAPParams($request, $response));
    $app->post('/mavis/ldap/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postLDAPParams($request, $response));
    $app->post('/mavis/ldap/group/search/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postLdapSearch($request, $response));
    $app->get('/mavis/ldap/group/list/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->getLdapList($request, $response));
    $app->post('/mavis/ldap/group/bind/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postLdapBind($request, $response));
    $app->get('/mavis/ldap/group/bind/ref/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->getBindRef($request, $response));
    $app->post('/mavis/ldap/group/bind/delete/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postBindDel($request, $response));
    $app->post('/mavis/ldap/group/bind/table/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postBindTable($request, $response));
    $app->post('/mavis/ldap/check/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postLDAPCheck($request, $response));
    $app->post('/mavis/ldap/test/', fn($request, $response) => $container->get(MAVISLDAPCtrl::class)->postTest($request, $response));
    $app->post('/mavis/otp/generate/secret/', fn($request, $response) => $container->get(MAVISOTPCtrl::class)->postOTPSecret($request, $response));
    $app->post('/mavis/otp/generate/url', fn($request, $response) => $container->get(MAVISOTPCtrl::class)->postOTPurl($request, $response));
    $app->get('/mavis/otp/', fn($request, $response) => $container->get(MAVISOTPCtrl::class)->getOTPParams($request, $response));
    $app->post('/mavis/otp/', fn($request, $response) => $container->get(MAVISOTPCtrl::class)->postOTPParams($request, $response));
    $app->post('/mavis/otp/check/', fn($request, $response) => $container->get(MAVISOTPCtrl::class)->postOTPCheck($request, $response));
    $app->get('/mavis/sms/', fn($request, $response) => $container->get(MAVISSMSCtrl::class)->getSMSParams($request, $response));
    $app->post('/mavis/sms/', fn($request, $response) => $container->get(MAVISSMSCtrl::class)->postSMSParams($request, $response));
    $app->post('/mavis/sms/send/', fn($request, $response) => $container->get(MAVISSMSCtrl::class)->postSMSSend($request, $response));
    $app->post('/mavis/sms/check/', fn($request, $response) => $container->get(MAVISSMSCtrl::class)->postSMSCheck($request, $response));
    $app->get('/mavis/local/', fn($request, $response) => $container->get(MAVISLocalCtrl::class)->getParams($request, $response));
    $app->post('/mavis/local/', fn($request, $response) => $container->get(MAVISLocalCtrl::class)->postParams($request, $response));
    $app->post('/mavis/local/check/', fn($request, $response) => $container->get(MAVISLocalCtrl::class)->postCheck($request, $response));

    // Logging Routes
    $app->post('/logging/datatables/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postLoggingDatatables($request, $response));
    $app->post('/logging/delete/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postLoggingDelete($request, $response));
    $app->post('/logging/delete/special/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postDelSpecial($request, $response));
    $app->post('/logging/miss/add/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postMissAdd($request, $response));
    $app->post('/logging/miss/delete/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postMissDel($request, $response));
    $app->post('/logging/miss/datatables/', fn($request, $response) => $container->get(APILoggingCtrl::class)->postMissTable($request, $response));

    // Notification Routes
    $app->get('/notification/settings/', fn($request, $response) => $container->get(APINotificationCtrl::class)->getSettings($request, $response));
    $app->post('/notification/settings/', fn($request, $response) => $container->get(APINotificationCtrl::class)->postSettings($request, $response));
    $app->post('/notification/post/logging/', fn($request, $response) => $container->get(APINotificationCtrl::class)->postDatatables($request, $response));
    $app->post('/notification/post/buffer/', fn($request, $response) => $container->get(APINotificationCtrl::class)->postBufferDatatables($request, $response));

    // Download Routes
    $app->get('/download/csv/', fn($request, $response) => $container->get(APIDownloadCtrl::class)->getDownloadCsv($request, $response));
    $app->get('/download/log/', fn($request, $response) => $container->get(APIDownloadCtrl::class)->getDownloadLog($request, $response));

    // HA Routes
    $app->post('/ha/init/', fn($request, $response) => $container->get(APIHACtrl::class)->postInitFromSlave($request, $response));
    $app->post('/ha/check/', fn($request, $response) => $container->get(APIHACtrl::class)->postCheck($request, $response));
    $app->post('/ha/cfg/apply/', fn($request, $response) => $container->get(APIHACtrl::class)->postApply($request, $response));
    $app->post('/ha/log/add/', fn($request, $response) => $container->get(APIHACtrl::class)->postLoggingEvent($request, $response));
    $app->post('/ha/slave/update/', fn($request, $response) => $container->get(APIHACtrl::class)->postSlaveUpdate($request, $response));
    $app->post('/ha/slave/update/do/', fn($request, $response) => $container->get(APIHACtrl::class)->postSlaveUpdateDo($request, $response));

    // Config Manager Routes
    $app->post('/confmanager/toggle/', fn($request, $response) => $container->get(ConfManager::class)->postToggle($request, $response));
    $app->get('/confmanager/info/', fn($request, $response) => $container->get(ConfManager::class)->getInfo($request, $response));
    $app->get('/confmanager/settings/preview/', fn($request, $response) => $container->get(ConfManager::class)->getPreview($request, $response));
    $app->get('/confmanager/settings/cron/', fn($request, $response) => $container->get(ConfManager::class)->getCron($request, $response));
    $app->post('/confmanager/settings/cron/', fn($request, $response) => $container->get(ConfManager::class)->postCron($request, $response));
    $app->post('/confmanager/datatables/', fn($request, $response) => $container->get(ConfManager::class)->postDatatables($request, $response));
    $app->post('/confmanager/log/datatables/', fn($request, $response) => $container->get(ConfManager::class)->postLogDatatables($request, $response));
    $app->get('/confmanager/dir/', fn($request, $response) => $container->get(ConfManager::class)->getDir($request, $response));
    $app->get('/confmanager/dir/exploer/', fn($request, $response) => $container->get(ConfManager::class)->getDirExploer($request, $response));
    $app->post('/confmanager/dir/add/', fn($request, $response) => $container->get(ConfManager::class)->postDirAdd($request, $response));
    $app->post('/confmanager/dir/delete/', fn($request, $response) => $container->get(ConfManager::class)->postDirDel($request, $response));
    $app->post('/confmanager/dir/mv/', fn($request, $response) => $container->get(ConfManager::class)->postDirMove($request, $response));
    $app->post('/confmanager/get/more/', fn($request, $response) => $container->get(ConfManager::class)->postMore($request, $response));
    $app->post('/confmanager/file/delete/', fn($request, $response) => $container->get(ConfManager::class)->postDel($request, $response));
    $app->get('/confmanager/file/download/', fn($request, $response) => $container->get(APIDownloadCtrl::class)->getDlCm($request, $response));
    $app->get('/confmanager/file/download/hash/', fn($request, $response) => $container->get(APIDownloadCtrl::class)->getCmHash($request, $response));
    $app->post('/confmanager/tacacs/', fn($request, $response) => $container->get(ConfManager::class)->postTacacs($request, $response));
    $app->get('/confmanager/diff/list/', fn($request, $response) => $container->get(ConfManager::class)->getDiffList($request, $response));
    $app->post('/confmanager/diff/brief/', fn($request, $response) => $container->get(ConfManager::class)->postDiffBrief($request, $response));
    $app->post('/confmanager/diff/', fn($request, $response) => $container->get(ConfManager::class)->postDiff($request, $response));
    $app->post('/confmanager/tacacs/aaa/', fn($request, $response) => $container->get(ConfManager::class)->postTacguiAAA($request, $response));

    // Config Manager Models
    $app->post('/confmanager/models/datatables/', fn($request, $response) => $container->get(ConfModels::class)->postDatatables($request, $response));
    $app->post('/confmanager/models/add/', fn($request, $response) => $container->get(ConfModels::class)->postAdd($request, $response));
    $app->get('/confmanager/models/edit/', fn($request, $response) => $container->get(ConfModels::class)->getEdit($request, $response));
    $app->post('/confmanager/models/edit/', fn($request, $response) => $container->get(ConfModels::class)->postEdit($request, $response));
    $app->post('/confmanager/models/delete/', fn($request, $response) => $container->get(ConfModels::class)->postDel($request, $response));
    $app->get('/confmanager/models/list/', fn($request, $response) => $container->get(ConfModels::class)->getList($request, $response));

    // Config Manager Devices
    $app->post('/confmanager/devices/datatables/', fn($request, $response) => $container->get(ConfDevices::class)->postDatatables($request, $response));
    $app->post('/confmanager/devices/add/', fn($request, $response) => $container->get(ConfDevices::class)->postAdd($request, $response));
    $app->get('/confmanager/devices/edit/', fn($request, $response) => $container->get(ConfDevices::class)->getEdit($request, $response));
    $app->post('/confmanager/devices/edit/', fn($request, $response) => $container->get(ConfDevices::class)->postEdit($request, $response));
    $app->post('/confmanager/devices/delete/', fn($request, $response) => $container->get(ConfDevices::class)->postDel($request, $response));
    $app->get('/confmanager/devices/list/', fn($request, $response) => $container->get(ConfDevices::class)->getList($request, $response));

    // Config Manager Groups
    $app->post('/confmanager/groups/datatables/', fn($request, $response) => $container->get(ConfGroups::class)->postDatatables($request, $response));
    $app->post('/confmanager/groups/add/', fn($request, $response) => $container->get(ConfGroups::class)->postAdd($request, $response));
    $app->get('/confmanager/groups/edit/', fn($request, $response) => $container->get(ConfGroups::class)->getEdit($request, $response));
    $app->post('/confmanager/groups/edit/', fn($request, $response) => $container->get(ConfGroups::class)->postEdit($request, $response));
    $app->post('/confmanager/groups/delete/', fn($request, $response) => $container->get(ConfGroups::class)->postDel($request, $response));
    $app->get('/confmanager/groups/list/', fn($request, $response) => $container->get(ConfGroups::class)->getList($request, $response));

    // Config Manager Credentials
    $app->post('/confmanager/credentials/datatables/', fn($request, $response) => $container->get(ConfigCredentials::class)->postDatatables($request, $response));
    $app->post('/confmanager/credentials/add/', fn($request, $response) => $container->get(ConfigCredentials::class)->postAdd($request, $response));
    $app->get('/confmanager/credentials/edit/', fn($request, $response) => $container->get(ConfigCredentials::class)->getEdit($request, $response));
    $app->post('/confmanager/credentials/edit/', fn($request, $response) => $container->get(ConfigCredentials::class)->postEdit($request, $response));
    $app->post('/confmanager/credentials/delete/', fn($request, $response) => $container->get(ConfigCredentials::class)->postDel($request, $response));
    $app->get('/confmanager/credentials/list/', fn($request, $response) => $container->get(ConfigCredentials::class)->getList($request, $response));

    // Config Manager Queries
    $app->post('/confmanager/queries/datatables/', fn($request, $response) => $container->get(ConfQueries::class)->postDatatables($request, $response));
    $app->post('/confmanager/queries/add/', fn($request, $response) => $container->get(ConfQueries::class)->postAdd($request, $response));
    $app->get('/confmanager/queries/edit/', fn($request, $response) => $container->get(ConfQueries::class)->getEdit($request, $response));
    $app->post('/confmanager/queries/edit/', fn($request, $response) => $container->get(ConfQueries::class)->postEdit($request, $response));
    $app->post('/confmanager/queries/delete/', fn($request, $response) => $container->get(ConfQueries::class)->postDel($request, $response));
    $app->post('/confmanager/queries/preview/', fn($request, $response) => $container->get(ConfQueries::class)->postPreview($request, $response));

    // Developer Routes
    $app->get('/dev/inc/js/dev.js', fn($request, $response) => $container->get(APIDevCtrl::class)->getDevJS($request, $response));
}
