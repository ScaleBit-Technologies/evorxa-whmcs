<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * The "server ready" client email and admin alerts.
 *
 * The client email is a normal WHMCS product template, so admins can edit the
 * wording (and add languages) under Setup > Email Templates.
 */
class Mailer
{
    const TEMPLATE = 'Cloud Server Ready';

    public static function install()
    {
        if (Capsule::table('tblemailtemplates')->where('name', self::TEMPLATE)->where('language', '')->count()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (self::templates() as $language => $tpl) {
            if (Capsule::table('tblemailtemplates')->where('name', self::TEMPLATE)->where('language', $language)->count()) {
                continue;
            }
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'product',
                'name' => self::TEMPLATE,
                'subject' => $tpl['subject'],
                'message' => $tpl['message'],
                'attachments' => '',
                'fromname' => '',
                'fromemail' => '',
                'disabled' => 0,
                'custom' => 1,
                'language' => $language,
                'copyto' => '',
                'blind_copy_to' => '',
                'plaintext' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Send the ready email exactly once per service. The claim is an atomic
     * UPDATE, so cron and a client's live panel can both call this safely.
     */
    public static function sendReady($serviceId, array $vars)
    {
        $claimed = Capsule::table(Schema::SERVICES)
            ->where('service_id', (int) $serviceId)
            ->whereNull('ready_notified_at')
            ->update(['ready_notified_at' => Repo::now()]);
        if (!$claimed) {
            return false;
        }
        self::install();
        $result = localAPI('SendEmail', [
            'messagename' => self::TEMPLATE,
            'id' => (int) $serviceId,
            'customvars' => base64_encode(serialize($vars)),
        ]);
        $ok = isset($result['result']) && $result['result'] === 'success';
        Repo::log($serviceId, 'email_ready', $ok, $ok ? 'Server ready email sent' : ('Email failed: ' . (isset($result['message']) ? $result['message'] : 'unknown')));
        return $ok;
    }

    /** Admin email + activity log entry, de-duplicated per $key for $ttl seconds. */
    public static function alertAdmin($key, $subject, $body, $ttl = 21600)
    {
        if (!Cache::acquire('alert:' . $key, $ttl)) {
            return;
        }
        if (function_exists('logActivity')) {
            logActivity('Evorxa: ' . $subject . ' - ' . $body);
        }
        if (function_exists('localAPI')) {
            localAPI('SendAdminEmail', [
                'customsubject' => '[Evorxa] ' . $subject,
                'custommessage' => '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>'
                    . '<p><a href="' . htmlspecialchars(self::adminUrl(), ENT_QUOTES, 'UTF-8') . '">Open Evorxa Manager</a></p>',
                'type' => 'system',
            ]);
        }
    }

    public static function adminUrl()
    {
        $base = rtrim((string) \App::getSystemURL(), '/');
        $admin = isset($GLOBALS['customadminpath']) && $GLOBALS['customadminpath'] ? $GLOBALS['customadminpath'] : 'admin';
        return $base . '/' . trim($admin, '/') . '/addonmodules.php?module=evorxa_manager';
    }

    /** Custom merge fields; native {$service_*} fields cover hostname, IP, username and password. */
    public static function readyVars($serviceId, array $inst, $app, $osLabel)
    {
        $base = rtrim((string) \App::getSystemURL(), '/');
        $windows = stripos((string) $osLabel, 'windows') !== false;
        return [
            'evx_os' => Util::clean($osLabel ?: (isset($inst['os']) ? $inst['os'] : ''), 120),
            'evx_is_windows' => $windows ? 1 : 0,
            'evx_location' => Util::clean(isset($inst['location']) ? $inst['location'] : '', 80),
            'evx_app_name' => $app ? Util::clean(isset($app['name']) ? $app['name'] : '', 80) : '',
            'evx_app_url' => $app ? Util::clean(isset($app['login_url']) ? $app['login_url'] : '', 255) : '',
            'evx_app_username' => $app ? Util::clean(isset($app['username']) ? $app['username'] : '', 120) : '',
            'evx_app_password' => $app ? Util::clean(isset($app['password']) ? $app['password'] : '', 255) : '',
            'evx_app_note' => $app ? Util::clean(isset($app['setup_note']) ? $app['setup_note'] : '', 400) : '',
            'evx_panel_url' => $base . '/clientarea.php?action=productdetails&id=' . (int) $serviceId,
        ];
    }

    private static function templates()
    {
        $row = function ($label, $value) {
            return '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;white-space:nowrap;">' . $label . '</td>'
                . '<td style="padding:6px 0;font-family:Menlo,Consolas,monospace;">' . $value . '</td></tr>';
        };

        $en = '<p>Dear {$client_first_name},</p>'
            . '<p>Your server <strong>{$service_product_name}</strong> is online and ready to use.</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . $row('Hostname', '{$service_domain}')
            . $row('IP address', '{$service_dedicated_ip}')
            . '{if $evx_os}' . $row('Operating system', '{$evx_os}') . '{/if}'
            . '{if $evx_location}' . $row('Location', '{$evx_location}') . '{/if}'
            . $row('Username', '{$service_username}')
            . $row('Password', '{$service_password}')
            . '</table>'
            . '{if $evx_is_windows}<p>Connect with Remote Desktop to <strong>{$service_dedicated_ip}</strong>.</p>'
            . '{else}<p>Connect over SSH: <code>ssh {$service_username}@{$service_dedicated_ip}</code></p>{/if}'
            . '{if $evx_app_name}<p><strong>{$evx_app_name}</strong> is installed on this server.</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . '{if $evx_app_url}' . $row('Sign-in address', '<a href="{$evx_app_url}">{$evx_app_url}</a>') . '{/if}'
            . '{if $evx_app_username}' . $row('Username', '{$evx_app_username}') . '{/if}'
            . '{if $evx_app_password}' . $row('Password', '{$evx_app_password}') . '{/if}'
            . '</table>'
            . '{if $evx_app_note}<p>{$evx_app_note}</p>{/if}{/if}'
            . '{if $evx_app_missing}<p><strong>{$evx_app_missing}</strong> could not be installed automatically. Our team has been notified and will help you; the server itself is ready to use.</p>{/if}'
            . '<p>Start, stop, reinstall, view usage graphs and manage the firewall from your client area:<br>'
            . '<a href="{$evx_panel_url}">{$evx_panel_url}</a></p>'
            . '<p>For your security, please change the password after your first sign-in.</p>'
            . '<p>{$signature}</p>';

        $ar = '<div dir="rtl" style="text-align:right;">'
            . '<p>عزيزنا {$client_first_name}،</p>'
            . '<p>خادمك <strong>{$service_product_name}</strong> يعمل الآن وجاهز للاستخدام.</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . $row('اسم المضيف', '{$service_domain}')
            . $row('عنوان IP', '{$service_dedicated_ip}')
            . '{if $evx_os}' . $row('نظام التشغيل', '{$evx_os}') . '{/if}'
            . '{if $evx_location}' . $row('الموقع', '{$evx_location}') . '{/if}'
            . $row('اسم المستخدم', '{$service_username}')
            . $row('كلمة المرور', '{$service_password}')
            . '</table>'
            . '{if $evx_is_windows}<p>اتصل عبر سطح المكتب البعيد (RDP) بالعنوان <strong>{$service_dedicated_ip}</strong>.</p>'
            . '{else}<p>اتصل عبر SSH: <code dir="ltr">ssh {$service_username}@{$service_dedicated_ip}</code></p>{/if}'
            . '{if $evx_app_name}<p>تم تثبيت <strong>{$evx_app_name}</strong> على هذا الخادم.</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">'
            . '{if $evx_app_url}' . $row('رابط الدخول', '<a href="{$evx_app_url}">{$evx_app_url}</a>') . '{/if}'
            . '{if $evx_app_username}' . $row('اسم المستخدم', '{$evx_app_username}') . '{/if}'
            . '{if $evx_app_password}' . $row('كلمة المرور', '{$evx_app_password}') . '{/if}'
            . '</table>'
            . '{if $evx_app_note}<p dir="ltr">{$evx_app_note}</p>{/if}{/if}'
            . '{if $evx_app_missing}<p>تعذر تثبيت <strong>{$evx_app_missing}</strong> تلقائياً. تم إبلاغ فريقنا وسيساعدك، والخادم نفسه جاهز للاستخدام.</p>{/if}'
            . '<p>يمكنك تشغيل الخادم وإيقافه وإعادة تثبيته ومتابعة الاستهلاك وإدارة الجدار الناري من منطقة العملاء:<br>'
            . '<a href="{$evx_panel_url}">{$evx_panel_url}</a></p>'
            . '<p>لأمانك، يرجى تغيير كلمة المرور بعد أول تسجيل دخول.</p>'
            . '<p>{$signature}</p></div>';

        return [
            '' => ['subject' => 'Your server {$service_domain} is ready', 'message' => $en],
            'arabic' => ['subject' => 'خادمك {$service_domain} جاهز', 'message' => $ar],
        ];
    }
}
