<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Media\HttpFoundation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BinaryResourceResponse implements the same logic as the Symfony BinaryFileResponse only for resource.
 */
class BinaryResourceResponse extends Response
{
    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $mimeType;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var int
     */
    protected $maxlen;

    /**
     * Constructor.
     *
     * @param resource            $resource           The resource to stream
     * @param int                 $size               The resource size
     * @param string              $mimeType           The resource mimeType
     * @param int                 $status             The response status code
     * @param array               $headers            An array of response headers
     * @param bool                $public             Files are public by default
     */
    public function __construct($resource, $size, $mimeType, $status = 200, $headers = array(), $public = true)
    {
        $this->setResource($resource, $size, $mimeType);
        parent::__construct(null, $status, $headers);

        if ($public) {
            $this->setPublic();
        }
    }

    /**
     * @param resource $resource
     * @param int $size
     * @param string $mimeType
     */
    protected function setResource($resource, $size, $mimeType)
    {
        $this->resource = $resource;
        $this->mimeType = $mimeType;
        $this->size = $size;
    }

    /**
     * @param resource            $resource           The file to stream
     * @param int                 $size               The resource size
     * @param string              $mimeType           The resource mimeType
     * @param int                 $status             The response status code
     * @param array               $headers            An array of response headers
     * @param bool                $public             Files are public by default
     *
     * @return BinaryResourceResponse The created response
     */
    public static function create($resource, $size, $mimeType, $status = 200, $headers = array(), $public = true)
    {
        return new static($resource, $size, $mimeType, $status, $headers, $public);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request)
    {
        $this->headers->set('Content-Length', $this->size);

        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe() ? 'bytes' : 'none');
        }

        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->mimeType ?: 'application/octet-stream');
        }

        if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        $this->ensureIEOverSSLCompatibility($request);

        $this->offset = 0;
        $this->maxlen = -1;

        if ($request->headers->has('Range')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->getEtag() == $request->headers->get('If-Range')) {
                $range = $request->headers->get('Range');
                $fileSize = $this->size;

                list($start, $end) = explode('-', substr($range, 6), 2) + array(0);

                $end = ('' === $end) ? $fileSize - 1 : (int) $end;

                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end = $fileSize - 1;
                } else {
                    $start = (int) $start;
                }

                if ($start <= $end) {
                    if ($start < 0 || $end > $fileSize - 1) {
                        $this->setStatusCode(416);
                    } elseif ($start !== 0 || $end !== $fileSize - 1) {
                        $this->maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                        $this->offset = $start;

                        $this->setStatusCode(206);
                        $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
                        $this->headers->set('Content-Length', $end - $start + 1);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Sends the file.
     */
    public function sendContent()
    {
        if (!$this->isSuccessful()) {
            parent::sendContent();

            return;
        }

        if (0 === $this->maxlen) {
            return;
        }

        $out = fopen('php://output', 'wb');

        stream_copy_to_stream($this->resource, $out, $this->maxlen, $this->offset);

        fclose($out);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryResourceResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    public function getContent()
    {
        return false;
    }
}