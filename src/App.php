<?php

/**
 * Copyright (c) 2021 Stanislav Janů (https://janu.software)
 */

declare(strict_types=1);

namespace JanuSoftware\MediaServe;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Nette\Http\Request;
use Nette\Http\RequestFactory;
use Nette\Http\Response;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Safe\Exceptions\FileinfoException;
use Safe\Exceptions\FilesystemException;
use function Safe\mime_content_type;


class App
{
	final public const CACHE = '/temp/cache';
	public Request $request;


	public function __construct(
		public string $rootDir,
	) {
		$factory = new RequestFactory;
		$this->request = $factory->fromGlobals();
	}


	/**
	 * @throws GuzzleException
	 * @throws FileinfoException
	 * @throws FilesystemException
	 * @throws Exception
	 */
	public function runCloudinaryCache(): void
	{
		$headers = $this->request->getHeaders();
		$url = $this->request->getUrl();
		$urlHash = md5($url->getAbsoluteUrl());
		$subFolder = Strings::substring($urlHash, 0, 2);
		$cloudinaryUrl = $url->withHost('res.cloudinary.com');

		if (str_contains($url->getPath(), 'e_vectorize')) {
			$extension = 'svg';
		} elseif (isset($headers['accept']) && str_contains((string) $headers['accept'], 'image/avif')) {
			$extension = 'avif';
		} elseif (isset($headers['accept']) && str_contains((string) $headers['accept'], 'image/webp')) {
			$extension = 'webp';
		} else {
			$extension = pathinfo($url->getPath(), PATHINFO_EXTENSION);
			if (Strings::length($extension) === 0) {
				$extension = 'dat';
			}
		}

		$extension = Strings::lower($extension);

		$oldCacheFile = self::CACHE . '/' . $urlHash . '.' . $extension;
		$cacheFile = self::CACHE . '/' . $subFolder . '/' . $urlHash . '.' . $extension;

		if (!file_exists($this->rootDir . $cacheFile) && file_exists($this->rootDir . $oldCacheFile)) {
			FileSystem::copy($this->rootDir . $oldCacheFile, $this->rootDir . $cacheFile);
			FileSystem::delete($this->rootDir . $oldCacheFile);
		}

		if (!file_exists($this->rootDir . $cacheFile)) {
			$client = new Client([
				'headers' => $headers,
			]);
			try {
				$absoluteUrl = $cloudinaryUrl->getAbsoluteUrl();
				if(!str_contains($absoluteUrl, '.jpg')) {
					$absoluteUrl .= '.jpg';
				}
				$response = $client->get(urldecode($absoluteUrl));
				FileSystem::write($this->rootDir . $cacheFile, $response->getBody()->getContents());
			} catch (ClientException $e) {
				echo $e->getMessage();
			}
		}

		$fileResponse = new FileResponse($this->rootDir . $cacheFile, $url->getPath(), mime_content_type($this->rootDir . $cacheFile), false);
		$fileResponse->send($this->request, new Response);
	}
}
