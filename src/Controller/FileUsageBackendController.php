<?php

declare(strict_types=1);

namespace Nfc036\ContaoFileUsageBundle\Controller;

use Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper;
use Contao\CoreBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="%contao.backend.route_prefix%", defaults={
 *     "_scope" = "backend",
 *     "_token_check" = true,
 * })
 */
class FileUsageBackendController extends AbstractController
{
    /**
     * @Route("/fileUsage", name="file_usage_file_show")
     */
    public function showAction(): Response
    {
        $this->initializeContaoFramework();
        $controller = new FileUsageHelper();
        return $controller->run();
    }

    public function updateFileUsage(): Response {
      $this->initializeContaoFramework();
      $controller = new FileUsageHelper();
      return $controller->runUpdate();
    }
}
