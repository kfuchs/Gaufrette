<?php

namespace Gaufrette\FileStream;

use Gaufrette\FileStream;
use Gaufrette\Filesystem;
use Gaufrette\StreamMode;

class InMemoryBuffer implements FileStream
{
    private $key;
    private $filesystem;
    private $mode;
    private $content;
    private $numBytes;
    private $position;
    private $synchronized;

    /**
     * Constructor
     *
     * @param  File $file
     */
    public function __construct($key, Filesystem $filesystem)
    {
        $this->key        = $key;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritDoc}
     */
    public function open(StreamMode $mode)
    {
        $this->mode = $mode;

        $exists = $this->filesystem->has($this->key);

        if (($exists && !$mode->allowsExistingFileOpening())
            || (!$exists && !$mode->allowsNewFileOpening())) {
            return false;
        }

        if ($mode->impliesExistingContentDeletion()) {
            $this->filesystem->write($this->key, '', true);
            $this->content = '';
        } else {
            $this->content = $this->filesystem->read($this->key);
        }

        $this->numBytes = strlen($this->content);
        $this->position = $mode->impliesPositioningCursorAtTheEnd() ? $this->numBytes : 0;

        $this->synchronized = true;

        return true;
    }

    public function read($count)
    {
        if (false === $this->mode->allowsRead()) {
            throw new \LogicException('The stream does not allow read.');
        }

        if (0 === $count) {
            return '';
        }

        $chunk = substr($this->content, $this->position, $count);

        $this->position+= strlen($chunk);

        return $chunk;
    }

    public function write($data)
    {
        if (false === $this->mode->allowsWrite()) {
            throw new \LogicException('The stream does not allow write.');
        }

        if ('' === $data) {
            return 0;
        }

        $numWrittenBytes = strlen($data);
        $newPosition     = $this->position + $numWrittenBytes;
        $newNumBytes     = $newPosition > $this->numBytes ? $newPosition : $this->numBytes;

        if ($this->eof()) {
            $this->numBytes+= $numWrittenBytes;
            $this->content.= $data;
        } else {
            $before = substr($this->content, 0, $this->position);
            $after  = $newNumBytes > $newPosition ? substr($this->content, $newPosition) : '';
            $this->content  = $before . $data . $after;
        }

        $this->position     = $newPosition;
        $this->numBytes     = $newNumBytes;
        $this->synchronized = false;

        return $numWrittenBytes;
    }

    public function close()
    {
        if ( ! $this->synchronized) {
            $this->flush();
        }
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position+= $offset;
                break;
            case SEEK_END:
                $this->position = $this->numBytes + $offset;
                break;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'The $whence "%s" is not supported.',
                    $whence
                ));
        }
    }

    public function tell()
    {
        return $this->position;
    }

    public function flush()
    {
        if ($this->synchronized) {
            return true;
        }

        try {
            $this->filesystem->write($this->key, $this->content, true);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function eof()
    {
        return $this->position >= $this->numBytes;
    }
}
