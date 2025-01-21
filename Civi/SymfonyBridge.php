<?php

namespace Civi;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * This class exists to bridge the core contract for PEAR with Symfony.
 */
class SymfonyBridge {
  private array $params;
  private string $driver;
  private $mailer;

  public function __construct(string $driver, array $params, $mailer) {
    $this->params = $params;
    $this->driver = $driver;
    $this->mailer = $mailer;
  }

  /**
   * Implements Mail::send() function using SMTP.
   *
   * @param mixed $recipients Either a comma-seperated list of recipients
   *              (RFC822 compliant), or an array of recipients,
   *              each RFC822 valid. This may contain recipients not
   *              specified in the headers, for Bcc:, resending
   *              messages, etc.
   *
   * @param array $headers The array of headers to send with the mail, in an
   *              associative array, where the array key is the
   *              header name (e.g., 'Subject'), and the array value
   *              is the header value (e.g., 'test'). The header
   *              produced from those values would be 'Subject:
   *              test'.
   *
   * @param string $body The full text of the message body, including any
   *               MIME parts, etc.
   *
   * @return mixed Returns true on success, or a PEAR_Error
   *               containing a descriptive error message on
   *               failure.
   */
  public function send($recipients, $headers, $body, $originalValues) {
    \CRM_Utils_Mail_Logger::filter($this->mailer, $recipients, $headers, $body);
    $email = (new Email())
      ->from($headers['From'])
      ->to($headers['To'])
      ->replyTo($headers['Reply-To'])
      ->subject($headers['Subject']);
    if (!empty($originalValues['text'])) {
      $email->text($originalValues['text']);
    }
    if (!empty($originalValues['html'])) {
      $email->html($originalValues['html']);
    }
    if (!empty($headers['Cc'])) {
      $email->cc($headers['Cc']);
    }

    if (!empty($headers['Bcc'])) {
      $email->bcc($headers['Bcc']);
    }
    foreach ($originalValues['attachments'] ?? [] as $attachment) {
      $email->addPart(new DataPart(new File($attachment['fullPath']), $attachment['fileName'], $attachment['mem_type']));
    }
    $dsn = $this->getDsn();

    $transport = Transport::fromDsn($dsn);
    $originalTimeout = ini_set('default_socket_timeout', \Civi::settings()->get('symfony_mail_timeout'));
    try {
      $mailer = new Mailer($transport);
      $mailer->send($email);
    }
    finally {
      ini_set('default_socket_timeout', $originalTimeout);
    }
    return TRUE;
  }

  public function getDriver() {
    return 'symfony_mailer';
  }

  /**
   * @return string
   */
  public function getDsn(): string {
    if (!empty($this->params['dsn'])) {
      // If the site has the right composer package installed then
      // as long as they can get the dsn into the setting they
      // could use any of the available providers.
      // https://symfony.com/doc/current/mailer.html#creating-sending-messages
      return $this->params['dsn'];
    }
    $dsn = '';
    if ($this->driver === 'smtp') {
      $credentials = '';
      if ($this->params['auth']) {
        $credentials = implode(':', [$this->params['auth']['username'], $this->params['auth']['password']]);
      }
      $hosts = explode(' ', $this->params['host']);
      if (count($hosts) === 1) {
        $dsn = 'smtp://' . $credentials . '@' . $this->params['host'] . ':' . $this->params['port'];
      }
      else {
        $servers = [];
        foreach ($hosts as $host) {
          $servers[] = 'smtp://' . $credentials . '@' . $host . ':' . $this->params['port'];
        }
        $dsn = 'failover(' . implode(' ', $servers) . ')';
      }
    }
    if ($this->driver === 'mail') {
      $dsn = 'native://default';
    }

    if ($this->driver === 'sendmail') {
      $dsn = 'sendmail://default?command=' . $this->params['send_mail_path'];
      if (!empty($this->params['sendmail_args'])) {
        $dsn .= urlencode(' ' . implode(' ', $this->params['sendmail_args']));
      }
    }
    return $dsn;
  }

}
