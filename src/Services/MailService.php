<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Wrapper minimalista de PHPMailer para enviar correos transaccionales.
 *
 * Filosofía:
 *  - Si la config SMTP está incompleta (MAIL_HOST vacío), isConfigured() devuelve
 *    false. Los callers preguntan ANTES de mandar para evitar el costo de armar
 *    el PHPMailer si no hay nada configurado.
 *  - Si el envío falla (SMTP down, credenciales mal, etc.), send() devuelve false
 *    y loguea via error_log. NUNCA throw — es responsabilidad del caller decidir
 *    si la falla del email rompe el flujo (típicamente: no rompe).
 */
class MailService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->fromAddress !== '';
    }

    /**
     * Envía un email HTML. Devuelve true si el SMTP aceptó el mensaje, false si falló.
     * Failures se loguean a error_log con el contexto.
     *
     * $embeddedImages: lista opcional de imágenes inline referenciadas en el HTML
     * vía `<img src="cid:CID">`. Cada item: ['cid' => string, 'data' => binary,
     * 'filename' => string, 'mime' => string]. Usamos CIDs en vez de `data:` URIs
     * porque Gmail/Outlook web bloquean estos últimos.
     *
     * $attachments: lista opcional de adjuntos descargables. Cada item:
     * ['data' => binary, 'filename' => string, 'mime' => string].
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $embeddedImages = [],
        array $attachments = [],
    ): bool {
        if (!$this->isConfigured()) {
            error_log('[MailService] No enviado: SMTP no configurado.');
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = $this->port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            // Timeouts cortos: no queremos que el botón "Marcar canjeada" se quede colgado
            // 30 segundos si el SMTP no responde.
            $mail->Timeout = 8;

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo($this->fromAddress, $this->fromName);

            foreach ($embeddedImages as $img) {
                $mail->addStringEmbeddedImage(
                    (string) $img['data'],
                    (string) $img['cid'],
                    (string) ($img['filename'] ?? 'image.png'),
                    PHPMailer::ENCODING_BASE64,
                    (string) ($img['mime'] ?? 'image/png'),
                );
            }
            foreach ($attachments as $att) {
                $mail->addStringAttachment(
                    (string) $att['data'],
                    (string) ($att['filename'] ?? 'attachment'),
                    PHPMailer::ENCODING_BASE64,
                    (string) ($att['mime'] ?? 'application/octet-stream'),
                );
            }

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[MailService] Falló enviar a %s subject=%s: %s',
                $toEmail,
                $subject,
                $e->getMessage()
            ));
            return false;
        }
    }
}
