<?php

namespace Rsvpify\LaravelMailAutoEmbed\Embedder;

use Swift_Image;
use Swift_Message;
use Swift_EmbeddedFile;
use Illuminate\Support\Str;
use Rsvpify\LaravelMailAutoEmbed\Models\EmbeddableEntity;

class AttachmentEmbedder extends Embedder
{
    /**
     * @var  Swift_Message
     */
    protected $message;

    /**
     * AttachmentEmbedder constructor.
     *
     * @param  Swift_Message $message
     */
    public function __construct(Swift_Message $message)
    {
        $this->message = $message;
    }

    /**
     * @param  string  $url
     */
    public function fromUrl($url)
    {
        $filePath = str_replace(url('/'), public_path('/'), $url);

        if (! file_exists($filePath)) {
            if ($embeddedFromRemoteUrl = $this->fromRemoteUrl($filePath)) {
                return $embeddedFromRemoteUrl;
            }

            return $url;
        }

        return $this->embed(
            Swift_Image::fromPath($filePath)
        );
    }

    /**
     * @param  string  $url
     */
    public function fromRemoteUrl($url)
    {
        if ($this->isUrlInWhitelist($url)) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $raw = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpcode == 200) {
                return $this->embed(
                    new Swift_Image($raw, Str::random(10), $contentType)
                );
            }
        }

        return null;
    }

    /**
     * @param  EmbeddableEntity  $entity
     */
    public function fromEntity(EmbeddableEntity $entity)
    {
        return $this->embed(
            new Swift_EmbeddedFile(
                $entity->getRawContent(),
                $entity->getFileName(),
                $entity->getMimeType()
            )
        );
    }

    /**
     * @param  string $url
     *
     * @return bool
     */
    public function isUrlInWhitelist($url)
    {
        if (strpos($url, 'http') !== 0) {
            return false;
        }

        $whitelistedUrls = config('mail-auto-embed.whitelist', []);

        foreach ($whitelistedUrls as $whitelistedUrl) {
            if (strpos($url, $whitelistedUrl) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Swift_EmbeddedFile  $attachment
     *
     * @return string
     */
    protected function embed(Swift_EmbeddedFile $attachment)
    {
        return $this->message->embed($attachment);
    }
}
