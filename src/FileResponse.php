<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace JanuSoftware\MediaServe;

use Exception;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;
use Nette\Utils\Strings;
use Safe\Exceptions\FilesystemException;
use function Safe\fclose;
use function Safe\filesize;
use function Safe\fopen;
use function Safe\fread;


final class FileResponse
{
	use SmartObject;

	public bool $resuming = true;

	private readonly string $file;

	private readonly string $contentType;

	private readonly string $name;


	public function __construct(
		string $file,
		string $name = null,
		string $contentType = null,
		private readonly bool $forceDownload = true,
	) {
		if (!is_file($file) || !is_readable($file)) {
			throw new Exception("File '$file' doesn't exist or is not readable.");
		}

		$this->file = $file;
		$this->name = $name ?? basename($file);
		$this->contentType = $contentType ?? 'application/octet-stream';
	}


	/**
	 * Returns the path to a downloaded file.
	 */
	public function getFile(): string
	{
		return $this->file;
	}


	/**
	 * Returns the file name.
	 */
	public function getName(): string
	{
		return $this->name;
	}


	/**
	 * Returns the MIME content type of a downloaded file.
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}


	/**
	 * Sends response to output.
	 *
	 * @throws FilesystemException
	 */
	public function send(IRequest $httpRequest, IResponse $httpResponse): void
	{
		$httpResponse->setContentType($this->contentType);
		$httpResponse->setHeader(
			'Content-Disposition',
			($this->forceDownload ? 'attachment' : 'inline')
				. '; filename="' . $this->name . '"'
				. '; filename*=utf-8\'\'' . rawurlencode($this->name),
		);

		$filesize = $length = filesize($this->file);
		$handle = fopen($this->file, 'r');

		if ($this->resuming) {
			$httpResponse->setHeader('Accept-Ranges', 'bytes');
			$matches = Strings::match((string) $httpRequest->getHeader('Range'), '#^bytes=(\d*)-(\d*)$#D');
			if ($matches !== null) {
				[, $start, $end] = $matches;
				if ($start === '') {
					$start = max(0, $filesize - $end);
					$end = $filesize - 1;

				} elseif ($end === '' || $end > $filesize - 1) {
					$end = $filesize - 1;
				}
				if ($end < $start) {
					$httpResponse->setCode(416); // requested range not satisfiable
					return;
				}

				$httpResponse->setCode(206);
				$httpResponse->setHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $filesize);
				$length = $end - $start + 1;
				fseek($handle, (int) $start);

			} else {
				$httpResponse->setHeader('Content-Range', 'bytes 0-' . ($filesize - 1) . '/' . $filesize);
			}
		}

		$httpResponse->setHeader('Content-Length', (string) $length);
		while (!feof($handle) && $length > 0) {
			echo $s = fread($handle, min(4_000_000, $length));
			$length -= strlen($s);
		}
		fclose($handle);
	}
}
