<?php

namespace App\Service;

use App\Entity\Mission;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PDFGenerator
{
    private Environment $twig;
    private string $uploadDir;

    public function __construct(Environment $twig, string $uploadDir)
    {
        $this->twig = $twig;
        $this->uploadDir = $uploadDir;
    }

    /**
     * Generate PDF mission report after validated checkout
     * @return string Path to generated PDF file
     */
    public function generateMissionReport(Mission $mission): string
    {
        // Configure Dompdf options
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);

        // Initialize Dompdf
        $dompdf = new Dompdf($options);

        // Render Twig template
        $html = $this->twig->render('pdf/mission_report.html.twig', [
            'mission' => $mission,
        ]);

        // Load HTML
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render PDF
        $dompdf->render();

        // Generate filename with mission ID and timestamp
        $filename = sprintf(
            'mission_%d_%s.pdf',
            $mission->getId(),
            (new \DateTime())->format('YmdHis')
        );

        // Define storage path
        $storagePath = $this->uploadDir . '/reports';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . '/' . $filename;

        // Save PDF to file
        file_put_contents($filePath, $dompdf->output());

        return $filePath;
    }

    /**
     * Generate PDF and return as download response
     * @return string PDF content as string
     */
    public function generateMissionReportForDownload(Mission $mission): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('pdf/mission_report.html.twig', [
            'mission' => $mission,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
