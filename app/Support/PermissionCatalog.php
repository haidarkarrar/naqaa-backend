<?php

namespace App\Support;

final class PermissionCatalog
{
    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_ACTIVATE = 'users.activate';
    public const USERS_ASSIGN_ROLES = 'users.assign_roles';
    public const USERS_ASSIGN_PERMISSIONS = 'users.assign_permissions';

    public const ROLES_VIEW = 'roles.view';
    public const ROLES_CREATE = 'roles.create';
    public const ROLES_UPDATE = 'roles.update';
    public const ROLES_DELETE = 'roles.delete';
    public const ROLES_ASSIGN_PERMISSIONS = 'roles.assign_permissions';

    public const ADMISSIONS_LIST_ASSIGNED = 'admissions.list.assigned';
    public const ADMISSIONS_LIST_ALL = 'admissions.list.all';
    public const ADMISSIONS_VIEW_DETAIL_ASSIGNED = 'admissions.view.detail.assigned';
    public const ADMISSIONS_VIEW_DETAIL_ALL = 'admissions.view.detail.all';
    public const ADMISSIONS_HISTORY_VIEW = 'admissions.history.view';
    public const ADMISSIONS_FORM_EDIT_ASSIGNED = 'admissions.form.edit.assigned';
    public const ADMISSIONS_FORM_EDIT_ALL = 'admissions.form.edit.all';
    public const ADMISSIONS_PATIENT_EDIT = 'admissions.patient.edit';
    public const ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED = 'admissions.attachments.manage.assigned';
    public const ADMISSIONS_ATTACHMENTS_MANAGE_ALL = 'admissions.attachments.manage.all';
    public const ADMISSIONS_STATUS_UPDATE = 'admissions.status.update';
    public const ADMISSIONS_STATUS_UPDATE_BATCH = 'admissions.status.update.batch';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_DOCTOR = 'doctor';
    public const ROLE_NURSE = 'nurse';

    public static function all(): array
    {
        return [
            self::USERS_VIEW,
            self::USERS_CREATE,
            self::USERS_UPDATE,
            self::USERS_ACTIVATE,
            self::USERS_ASSIGN_ROLES,
            self::USERS_ASSIGN_PERMISSIONS,
            self::ROLES_VIEW,
            self::ROLES_CREATE,
            self::ROLES_UPDATE,
            self::ROLES_DELETE,
            self::ROLES_ASSIGN_PERMISSIONS,
            self::ADMISSIONS_LIST_ASSIGNED,
            self::ADMISSIONS_LIST_ALL,
            self::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
            self::ADMISSIONS_VIEW_DETAIL_ALL,
            self::ADMISSIONS_HISTORY_VIEW,
            self::ADMISSIONS_FORM_EDIT_ASSIGNED,
            self::ADMISSIONS_FORM_EDIT_ALL,
            self::ADMISSIONS_PATIENT_EDIT,
            self::ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED,
            self::ADMISSIONS_ATTACHMENTS_MANAGE_ALL,
            self::ADMISSIONS_STATUS_UPDATE,
            self::ADMISSIONS_STATUS_UPDATE_BATCH,
        ];
    }

    public static function doctorDefaults(): array
    {
        return [
            self::ADMISSIONS_LIST_ASSIGNED,
            self::ADMISSIONS_VIEW_DETAIL_ASSIGNED,
            self::ADMISSIONS_HISTORY_VIEW,
            self::ADMISSIONS_FORM_EDIT_ASSIGNED,
            self::ADMISSIONS_ATTACHMENTS_MANAGE_ASSIGNED,
        ];
    }

    public static function nurseDefaults(): array
    {
        return [
            self::ADMISSIONS_LIST_ALL,
            self::ADMISSIONS_VIEW_DETAIL_ALL,
            self::ADMISSIONS_HISTORY_VIEW,
            self::ADMISSIONS_FORM_EDIT_ALL,
            self::ADMISSIONS_PATIENT_EDIT,
            self::ADMISSIONS_ATTACHMENTS_MANAGE_ALL,
            self::ADMISSIONS_STATUS_UPDATE,
            self::ADMISSIONS_STATUS_UPDATE_BATCH,
        ];
    }
}
