<?php

declare(strict_types=1);

namespace Nfc036\ContaoFileUsageBundle\Controller;

use Nfc036\ContaoFileUsageBundle\Module\FileUsageHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;  // Contao>=4.9 provides its own AbstractController class under Contao\CoreBundle\Controller\AbstractController, we use the Symfony one for backwards compatibility
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/contao", defaults={
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
        $this->get('contao.framework')->initialize(); // The new AbstractController from Contao>=4.9 provides this via $this->initializeContaoFramework();

        $controller = new FileUsageHelper();

        return $controller->run();
    }
}