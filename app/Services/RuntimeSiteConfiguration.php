<?php

namespace App\Services;

use Illuminate\Mail\MailManager;

class RuntimeSiteConfiguration
{
    private const KEYS = [
        'site_name',
        'site_url',
        'timezone',
        'session_lifetime_minutes',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_scheme',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    public function __construct(
        private readonly SiteSettings $settings,
        private readonly MailManager $mail,
    ) {}

    public function apply(): void
    {
        $values = $this->settings->getMany(self::KEYS);
        $siteUrl = filled($values['site_url']) ? rtrim((string) $values['site_url'], '/') : null;
        $mailScheme = filled($values['mail_scheme']) ? (string) $values['mail_scheme'] : null;

        config([
            'app.name' => $values['site_name'],
            'kexi.display_timezone' => $values['timezone'],
            'session.lifetime' => $values['session_lifetime_minutes'],
            'mail.default' => $values['mail_mailer'],
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.scheme' => $mailScheme,
            'mail.mailers.smtp.require_tls' => $mailScheme === 'smtp',
            'mail.mailers.smtp.host' => $values['mail_host'],
            'mail.mailers.smtp.port' => $values['mail_port'],
            'mail.mailers.smtp.username' => $values['mail_username'],
            'mail.mailers.smtp.password' => $values['mail_password'],
            'mail.from.address' => $values['mail_from_address'],
            'mail.from.name' => $values['mail_from_name'],
        ]);

        $this->mail->forgetMailers();

        if ($siteUrl) {
            $host = parse_url($siteUrl, PHP_URL_HOST);

            config([
                'app.url' => $siteUrl,
                'mail.mailers.smtp.local_domain' => $host,
            ]);
        }
    }
}
