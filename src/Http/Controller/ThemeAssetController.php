<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Http\Response;
use Mt2Cms\Theme\ThemeAssetFile;

class ThemeAssetController extends Controller
{
    public function show(string $theme, string $asset): Response
    {
        $path = ThemeAssetFile::absolutePath(BASE_DIR . '/themes', $theme, rawurldecode($asset));

        if ($path === null) {
            return Response::notFound('');
        }

        return Response::cachedFile($path, ThemeAssetFile::mimeType($asset));
    }
}
