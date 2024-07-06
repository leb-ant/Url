<?php
namespace Lebant69\Url;

class Url
{
    public static function build(array $components, bool $trailingSlash = false): ?string
    {
        $path = '';
        $schema = empty($components['schema']) ? 'https' : $components['schema'];
        $host = $components['host'];

        if (!is_array($components['path'])) {
            $components['path'] = [$components['path']];
        }

        $components['path'] = array_filter($components['path']);

        if (empty($components['host']) || empty($components['path'])) {
            throw new \Exception("Host and path required");
        }

        foreach ($components['path'] as $part) {
            $path .= '/' . trim($part, " \t\n\r\0\x0B\\/");
        }

        if ($trailingSlash) {
            $path .= "/";
        }

        return sprintf("%s://%s%s", $schema, $host, $path);
    }

    public static function isSubDomainOf(string $subDomain, string $domain): bool
    {
        return self::getMasterHost($subDomain) == $domain;
    }

    public static function hostHasSSL(string $host): bool
    {
        if ($fp = @stream_socket_client("ssl://$host:443" , $errCode, $errStr, 1)) {
            fclose($fp);
            return true;
        } else {
            return false;
        }
    }

    public static function getMasterHost(string $host): string
    {
        $parts = explode('.', $host);
        $num = sizeof($parts);

        return $num > 2
            ? $parts[$num - 2] . '.' . $parts[$num - 1]
            : $host;
    }

	public static function addTrailingSlash(string $url): string
	{
		return rtrim($url, '/') . '/';
	}

	public static function concat(array $parts, array $params = []): string
    {
		$aUrl = [];
        $sUrl = '';

        $parts = array_values(array_filter($parts, 'strlen'));

        $trailingSlash = $params['trailingSlash'] ?? true;

        $parts[0] = str_replace('\\', '/', $parts[0]);
        $scheme = parse_url($parts[0], PHP_URL_SCHEME);

        if ($parts[0][0] === '/' && $parts[0][1] === '/') {
            $delimiter = '//';
        } elseif (!empty($scheme)) {
            $delimiter = '://';
        } else {
            $delimiter = '';
        }

        $parts[0] = str_replace($scheme . $delimiter, '', $parts[0]);

        foreach ($parts as $part) {
            $aUrl[] = trim($part, '/\\');
        }

        $sUrl = join('/', $aUrl);
        $ext = pathinfo($sUrl, PATHINFO_EXTENSION);

        if (empty($ext) && $trailingSlash) {
			$sUrl = self::addTrailingSlash($sUrl);
		}

        return $scheme . $delimiter . self::fixSlashes($sUrl);
	}

	public static function fixSlashes(string $s): string
	{
        return preg_replace('#/+#', '/', str_replace('\\', '/', $s));
	}

	public static function exist(string $url): bool
	{
		$oldContext = stream_context_get_options(stream_context_get_default());

		stream_context_set_default(
				[
					'http' => [
                        'method' => 'HEAD'
					]
				]
		);

		$headers = @get_headers($url, 1);
		stream_context_set_default($oldContext);

		if (!empty($headers)) {
			foreach ($headers as $key => $header) {
				if (is_numeric($key) && false !== strpos($header, '200')) {
					return true;
				}
			}
		}

		return false;
	}

	public static function encode(string $s): string
	{
		return rawurlencode(rawurldecode($s));
	}

	public static function getBase(string $url): string
	{
		$cutted = substr($url, 0, strrpos($url, self::SLASH)) . self::SLASH;
		$cuttedScheme = parse_url($cutted, PHP_URL_SCHEME);

		if (!empty($cuttedScheme)) {
			return $cutted;
		}

		if ($url[0] == self::SLASH && $url[1] == self::SLASH) {
			return 'http:' . $cutted;
		} else {
			$scheme = parse_url($url, PHP_URL_SCHEME);
			return empty($scheme) ? '' : $scheme . '://' . $cutted;
		}
	}

	public static function absolute(string $url, string $base): string
	{
		$base = rtrim($base, self::SLASH);
		$url  = ltrim($url, self::SLASH);

		if ($url[0] == '#' || $url[0] == '?') {
			return $base . self::SLASH . $url;
		}

		$scheme = parse_url($url, PHP_URL_SCHEME);

		if (!empty($scheme)) {
			return $url;
		}

		if ($url[0] == self::SLASH && $url[1] == self::SLASH) {
			return 'http:' . $url;
		}

		return $base . self::SLASH . $url;
	}

	public static function parse(
	    string $url,
        array $components = ['scheme', 'host', 'path'],
        $removeWWW = false,
        string $defaultScheme = ''
    ): array
	{
		$list     = [];
		$parsed   = parse_url($url);
		$granted  = ['scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'];

		foreach ($components as $idx) {
			if (in_array($idx, $granted)) {
				$list[$idx] = empty($parsed[$idx]) ? '' : $parsed[$idx];

				if (false !== $removeWWW && $idx == 'host') {
					$list[$idx] = (str_starts_with($list[$idx], 'www.')) ? substr($list[$idx], 4) : $list[$idx];
				}

				if ($idx == 'scheme' && empty($list[$idx])) {
					$list[$idx] = $defaultScheme;
				}
			}
		}

		return $list;
	}
}
