<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Entity\Ordonnance;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PdfGeneratorService
{
    public function __construct(private ParameterBagInterface $params) {}

    /**
     * Génère un PDF d'ordonnance pour une consultation donnée
     *
     * @param Consultation $consultation
     * @return string Le contenu PDF en tant que string
     */
    public function generateOrdonnancePdf(Consultation $consultation): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le contenu HTML
        $html = $this->renderOrdonnanceHtml($consultation);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Génère un PDF pour toutes les ordonnances d'une consultation
     *
     * @param Consultation $consultation
     * @return string Le contenu PDF en tant que string
     */
    public function generateAllOrdonnancesPdf(Consultation $consultation): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le contenu HTML pour toutes les ordonnances
        $html = $this->renderAllOrdonnancesHtml($consultation);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Rend le HTML d'une seule ordonnance
     */
    private function renderOrdonnanceHtml(Consultation $consultation): string
    {
        $ordonnances = $consultation->getOrdonnances();
        $patient = $consultation->getPatient();
        $medecin = $consultation->getMedecin();

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
                    background: #ffffff;
                    color: #1a202c;
                    line-height: 1.8;
                    margin: 0;
                    padding: 0;
                }
                .document {
                    max-width: 850px;
                    margin: 0 auto;
                    background: #ffffff;
                    overflow: hidden;
                    padding: 0;
                }
                .header {
                    background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
                    padding: 25px 30px;
                    color: white;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 0;
                }
                .header-left {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                .logo {
                    width: 70px;
                    height: 70px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    font-weight: 700;
                    color: white;
                }
                .clinic-info h1 {
                    font-size: 26px;
                    font-weight: 700;
                    margin: 0 0 5px 0;
                    letter-spacing: 1px;
                }
                .clinic-info p {
                    font-size: 12px;
                    opacity: 0.9;
                    margin: 2px 0;
                }
                .header-right {
                    text-align: right;
                    font-size: 12px;
                    opacity: 0.95;
                }
                .header-right p {
                    margin: 4px 0;
                }
                .content {
                    padding: 40px;
                }
                .main-title {
                    text-align: center;
                    font-size: 28px;
                    font-weight: 800;
                    color: #0066cc;
                    margin-bottom: 35px;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .section {
                    margin-bottom: 28px;
                    padding: 20px;
                    background: linear-gradient(135deg, #f8f9fa 0%, #f0f4f8 100%);
                    border-left: 5px solid #0066cc;
                    border-radius: 6px;
                }
                .section-title {
                    font-size: 13px;
                    font-weight: 800;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin-bottom: 16px;
                    letter-spacing: 1.5px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                }
                .info-item {
                    display: flex;
                    flex-direction: column;
                }
                .info-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .info-value {
                    font-size: 15px;
                    color: #1a202c;
                    font-weight: 600;
                }
                .medicaments-section {
                    margin-top: 35px;
                }
                .medicaments-title {
                    font-size: 13px;
                    font-weight: 800;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin-bottom: 20px;
                    letter-spacing: 1.5px;
                }
                .medicament-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f9fbfc 100%);
                    border: 2px solid #10b981;
                    border-radius: 6px;
                    padding: 18px;
                    margin-bottom: 12px;
                    page-break-inside: avoid;
                    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08);
                }
                .medicament-name {
                    font-size: 17px;
                    font-weight: 700;
                    color: #10b981;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .medicament-details {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 12px;
                    margin-bottom: 14px;
                }
                .medicament-detail {
                    padding: 12px;
                    background: #ffffff;
                    border-radius: 6px;
                    border-left: 4px solid #10b981;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                }
                .medicament-detail-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #6b7280;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .medicament-detail-value {
                    font-size: 14px;
                    color: #1a202c;
                    font-weight: 700;
                }
                .instructions {
                    background: #fff8f0;
                    border-left: 4px solid #f59e0b;
                    padding: 12px;
                    border-radius: 6px;
                    font-size: 13px;
                    color: #7c2d12;
                    line-height: 1.6;
                }
                .instructions-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #f59e0b;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .footer {
                    background: linear-gradient(135deg, #f8f9fa 0%, #f0f4f8 100%);
                    padding: 30px 40px;
                    border-top: 1px solid #e5e7eb;
                    text-align: center;
                }
                .validity-badge {
                    display: inline-block;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 25px;
                    font-weight: 700;
                    font-size: 14px;
                    margin-bottom: 20px;
                    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
                }
                .footer-content {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 20px;
                    margin-top: 20px;
                }
                .footer-item {
                    text-align: center;
                    font-size: 12px;
                }
                .footer-item-label {
                    font-weight: 700;
                    color: #0066cc;
                    margin-bottom: 4px;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .footer-item-value {
                    color: #1a202c;
                    font-size: 13px;
                }
                .legal-text {
                    margin-top: 20px;
                    font-size: 11px;
                    color: #6b7280;
                    font-style: italic;
                    line-height: 1.6;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="document">
                <div class="header">
                    <div class="header-left">
                        <div class="logo">A</div>
                        <div class="clinic-info">
                            <h1>AIDORA</h1>
                            <p>Medical Healthcare Platform</p>
                            <p>Professional Prescription Management</p>
                        </div>
                    </div>
                    <div class="header-right">
                        <p><strong>Date:</strong> ' . (new \DateTime())->format('d/m/Y') . '</p>
                        <p><strong>Time:</strong> ' . (new \DateTime())->format('H:i') . '</p>
                    </div>
                </div>

                <div class="content">
                    <div class="main-title">📋 PRESCRIPTION</div>

                    <div class="section">
                        <div class="section-title">📅 Consultation Details</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Date</span>
                                <span class="info-value">' . ($consultation->getDateConsultation() ? $consultation->getDateConsultation()->format('d/m/Y') : '—') . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Time</span>
                                <span class="info-value">' . ($consultation->getTimeSlot() ?? '—') . '</span>
                            </div>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <span class="info-label">Chief Complaint</span>
                                <span class="info-value">' . ($consultation->getMotif() ?? '') . '</span>
                            </div>
                        </div>
                    </div>';

        if ($patient) {
            $html .= '
                    <div class="section">
                        <div class="section-title">👤 Patient Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value">' . $patient->getName() . ' ' . $patient->getFamilyName() . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Age</span>
                                <span class="info-value">' . ($consultation->getAge() ?? 'N/A') . ' years</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value">' . ($consultation->getEmail() ?? 'N/A') . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value">' . ($consultation->getSex() ?? 'N/A') . '</span>
                            </div>
                        </div>
                    </div>';
        }

        if ($medecin) {
            $html .= '
                    <div class="section">
                        <div class="section-title">👨‍⚕️ Prescriber</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Doctor</span>
                                <span class="info-value">Dr. ' . $medecin->getName() . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Specialty</span>
                                <span class="info-value">' . ($medecin->getSpecialite() ?? 'General Practice') . '</span>
                            </div>
                        </div>
                    </div>';
        }

        if (!$ordonnances->isEmpty()) {
            $html .= '
                    <div class="medicaments-section">
                        <div class="medicaments-title">💊 Prescribed Medications</div>';

            foreach ($ordonnances as $ordonnance) {
                $medicaments = $ordonnance->getMedicaments();
                
                if ($medicaments && !$medicaments->isEmpty()) {
                    foreach ($medicaments as $medicament) {
                        $html .= '
                        <div class="medicament-card">
                            <div class="medicament-name">✓ ' . ($medicament->getMedicament() ?? '—') . '</div>
                            <div class="medicament-details">
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Dosage</div>
                                    <div class="medicament-detail-value">' . ($medicament->getDosage() ?? '—') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Duration</div>
                                    <div class="medicament-detail-value">' . ($medicament->getDuree() ?? '—') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Frequency</div>
                                    <div class="medicament-detail-value">As prescribed</div>
                                </div>
                            </div>';

                        if ($medicament->getInstructions()) {
                            $html .= '
                            <div class="instructions">
                                <div class="instructions-label">⚠️ Special Instructions</div>
                                ' . nl2br(htmlspecialchars($medicament->getInstructions())) . '
                            </div>';
                        }

                        $html .= '
                        </div>';
                    }
                } else {
                    // Fallback for old ordonnances
                    $html .= '
                        <div class="medicament-card">
                            <div class="medicament-name">✓ ' . ($ordonnance->getMedicament() ?? '—') . '</div>
                            <div class="medicament-details">
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Dosage</div>
                                    <div class="medicament-detail-value">' . ($ordonnance->getDosage() ?? '—') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Duration</div>
                                    <div class="medicament-detail-value">' . ($ordonnance->getDuree() ?? '—') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Frequency</div>
                                    <div class="medicament-detail-value">As prescribed</div>
                                </div>
                            </div>';

                    if ($ordonnance->getInstructions()) {
                        $html .= '
                            <div class="instructions">
                                <div class="instructions-label">⚠️ Special Instructions</div>
                                ' . nl2br(htmlspecialchars($ordonnance->getInstructions())) . '
                            </div>';
                    }

                    $html .= '
                        </div>';
                }
            }

            $html .= '
                    </div>';
        }

        $html .= '
                </div>

                <div class="footer">
                    <div class="validity-badge">✓ VALID PRESCRIPTION</div>
                    <div class="footer-content">
                        <div class="footer-item">
                            <div class="footer-item-label">Generated</div>
                            <div class="footer-item-value">' . (new \DateTime())->format('d/m/Y H:i') . '</div>
                        </div>
                        <div class="footer-item">
                            <div class="footer-item-label">Document ID</div>
                            <div class="footer-item-value">RX-' . ($consultation->getId() ?? '') . '</div>
                        </div>
                        <div class="footer-item">
                            <div class="footer-item-label">Validity</div>
                            <div class="footer-item-value">1 Year</div>
                        </div>
                    </div>
                    <div class="legal-text">
                        This is an official medical prescription. Present to pharmacy for medication dispensing.
                    </div>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }
    /**
     * Rend le HTML pour toutes les ordonnances d'une consultation
     */
    private function renderAllOrdonnancesHtml(Consultation $consultation): string
    {
        $ordonnances = $consultation->getOrdonnances();
        $patient = $consultation->getPatient();
        $medecin = $consultation->getMedecin();

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
                    background: #ffffff;
                    color: #1a202c;
                    line-height: 1.8;
                    margin: 0;
                    padding: 0;
                }
                .document {
                    max-width: 850px;
                    margin: 0 auto;
                    background: #ffffff;
                    overflow: hidden;
                    padding: 0;
                }
                .header {
                    background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
                    padding: 25px 30px;
                    color: white;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 0;
                }
                .header-left {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                .logo {
                    width: 70px;
                    height: 70px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    font-weight: 700;
                    color: white;
                }
                .clinic-info h1 {
                    font-size: 26px;
                    font-weight: 700;
                    margin: 0 0 5px 0;
                    letter-spacing: 1px;
                }
                .clinic-info p {
                    font-size: 12px;
                    opacity: 0.9;
                    margin: 2px 0;
                }
                .header-right {
                    text-align: right;
                    font-size: 12px;
                    opacity: 0.95;
                }
                .header-right p {
                    margin: 4px 0;
                }
                .content {
                    padding: 30px 30px;
                }
                .main-title {
                    text-align: center;
                    font-size: 24px;
                    font-weight: 800;
                    color: #0066cc;
                    margin: 0 0 25px 0;
                    padding: 0;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .section {
                    margin-bottom: 20px;
                    padding: 15px;
                    background: linear-gradient(135deg, #f8f9fa 0%, #f0f4f8 100%);
                    border-left: 5px solid #0066cc;
                    border-radius: 4px;
                    page-break-inside: avoid;
                }
                .section-title {
                    font-size: 13px;
                    font-weight: 800;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin-bottom: 16px;
                    letter-spacing: 1.5px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                }
                .info-item {
                    display: flex;
                    flex-direction: column;
                }
                .info-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .info-value {
                    font-size: 15px;
                    color: #1a202c;
                    font-weight: 600;
                }
                .medicaments-section {
                    margin-top: 20px;
                }
                .medicaments-title {
                    font-size: 13px;
                    font-weight: 800;
                    color: #0066cc;
                    text-transform: uppercase;
                    margin: 0 0 15px 0;
                    padding: 0;
                    letter-spacing: 1.5px;
                }
                .medicament-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f9fbfc 100%);
                    border: 2px solid #10b981;
                    border-radius: 6px;
                    padding: 18px;
                    margin-bottom: 12px;
                    page-break-inside: avoid;
                    box-shadow: 0 2px 8px rgba(16,185,129,0.08);
                }
                .medicament-name {
                    font-size: 17px;
                    font-weight: 700;
                    color: #10b981;
                    margin-bottom: 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .medicament-details {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 12px;
                    margin-bottom: 14px;
                }
                .medicament-detail {
                    padding: 12px;
                    background: #ffffff;
                    border-radius: 6px;
                    border-left: 4px solid #10b981;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                }
                .medicament-detail-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #6b7280;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .medicament-detail-value {
                    font-size: 14px;
                    color: #1a202c;
                    font-weight: 700;
                }
                .instructions {
                    background: #fff8f0;
                    border-left: 4px solid #f59e0b;
                    padding: 12px;
                    border-radius: 6px;
                    font-size: 13px;
                    color: #7c2d12;
                    line-height: 1.6;
                }
                .instructions-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: #f59e0b;
                    text-transform: uppercase;
                    margin-bottom: 6px;
                    letter-spacing: 0.5px;
                }
                .page-break {
                    page-break-before: always;
                    margin: 0;
                    padding: 0;
                }
                .footer {
                    background: linear-gradient(135deg, #f8f9fa 0%, #f0f4f8 100%);
                    padding: 20px 30px;
                    border-top: 1px solid #e5e7eb;
                    text-align: center;
                    page-break-inside: avoid;
                    margin-top: 20px;
                }
                .validity-badge {
                    display: inline-block;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 25px;
                    font-weight: 700;
                    font-size: 14px;
                    margin-bottom: 20px;
                    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
                }
                .footer-content {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 20px;
                    margin-top: 20px;
                }
                .footer-item {
                    text-align: center;
                    font-size: 12px;
                }
                .footer-item-label {
                    font-weight: 700;
                    color: #0066cc;
                    margin-bottom: 4px;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .footer-item-value {
                    color: #1a202c;
                    font-size: 13px;
                }
                .legal-text {
                    margin-top: 20px;
                    font-size: 11px;
                    color: #6b7280;
                    font-style: italic;
                    line-height: 1.6;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="document">
                <div class="header">
                    <div class="header-left">
                        <div class="logo">A</div>
                        <div class="clinic-info">
                            <h1>AIDORA</h1>
                            <p>Medical Healthcare Platform</p>
                            <p>Professional Prescription Management</p>
                        </div>
                    </div>
                    <div class="header-right">
                        <p><strong>Date:</strong> ' . (new \DateTime())->format('d/m/Y') . '</p>
                        <p><strong>Time:</strong> ' . (new \DateTime())->format('H:i') . '</p>
                    </div>
                </div>

                <div class="content">
                    <div class="main-title">📋 ALL PRESCRIPTIONS</div>

                    <div class="section">
                        <div class="section-title">📅 Consultation Details</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Date</span>
                                <span class="info-value">' . ($consultation->getDateConsultation() ? $consultation->getDateConsultation()->format('d/m/Y') : 'N/A') . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Time</span>
                                <span class="info-value">' . ($consultation->getTimeSlot() ?? 'N/A') . '</span>
                            </div>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <span class="info-label">Chief Complaint</span>
                                <span class="info-value">' . ($consultation->getMotif() ?? 'Not specified') . '</span>
                            </div>
                        </div>
                    </div>';

        if ($patient) {
            $html .= '
                    <div class="section">
                        <div class="section-title">👤 Patient Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value">' . $patient->getName() . ' ' . $patient->getFamilyName() . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Age</span>
                                <span class="info-value">' . ($consultation->getAge() ?? 'N/A') . ' years</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value">' . ($consultation->getEmail() ?? 'N/A') . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value">' . ($consultation->getSex() ?? 'N/A') . '</span>
                            </div>
                        </div>
                    </div>';
        }

        if ($medecin) {
            $html .= '
                    <div class="section">
                        <div class="section-title">👨‍⚕️ Prescriber</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Doctor</span>
                                <span class="info-value">Dr. ' . $medecin->getName() . '</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Specialty</span>
                                <span class="info-value">' . ($medecin->getSpecialite() ?? 'General Practice') . '</span>
                            </div>
                        </div>
                    </div>';
        }

        if (!$ordonnances->isEmpty()) {
            $html .= '
                    <div class="medicaments-section">
                        <div class="medicaments-title">💊 All Prescribed Medications</div>';

            $medicamentCount = 0;
            foreach ($ordonnances as $ordonnance) {
                $medicaments = $ordonnance->getMedicaments();
                
                if ($medicaments && !$medicaments->isEmpty()) {
                    foreach ($medicaments as $medicament) {
                        $medicamentCount++;
                        if ($medicamentCount > 1) {
                            $html .= '<div class="page-break"></div>';
                        }

                        $html .= '
                        <div class="medicament-card">
                            <div class="medicament-name">✓ ' . ($medicament->getMedicament() ?? 'Medication not specified') . '</div>
                            <div class="medicament-details">
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Dosage</div>
                                    <div class="medicament-detail-value">' . ($medicament->getDosage() ?? 'Not specified') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Duration</div>
                                    <div class="medicament-detail-value">' . ($medicament->getDuree() ?? 'Not specified') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Frequency</div>
                                    <div class="medicament-detail-value">As prescribed</div>
                                </div>
                            </div>';

                        if ($medicament->getInstructions()) {
                            $html .= '
                            <div class="instructions">
                                <div class="instructions-label">⚠️ Special Instructions</div>
                                ' . nl2br(htmlspecialchars($medicament->getInstructions())) . '
                            </div>';
                        }

                        $html .= '
                        </div>';
                    }
                } else {
                    // Fallback for old ordonnances
                    $medicamentCount++;
                    if ($medicamentCount > 1) {
                        $html .= '<div class="page-break"></div>';
                    }

                    $html .= '
                        <div class="medicament-card">
                            <div class="medicament-name">✓ ' . ($ordonnance->getMedicament() ?? 'Medication not specified') . '</div>
                            <div class="medicament-details">
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Dosage</div>
                                    <div class="medicament-detail-value">' . ($ordonnance->getDosage() ?? 'Not specified') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Duration</div>
                                    <div class="medicament-detail-value">' . ($ordonnance->getDuree() ?? 'Not specified') . '</div>
                                </div>
                                <div class="medicament-detail">
                                    <div class="medicament-detail-label">Frequency</div>
                                    <div class="medicament-detail-value">As prescribed</div>
                                </div>
                            </div>';

                    if ($ordonnance->getInstructions()) {
                        $html .= '
                            <div class="instructions">
                                <div class="instructions-label">⚠️ Special Instructions</div>
                                ' . nl2br(htmlspecialchars($ordonnance->getInstructions())) . '
                            </div>';
                    }

                    $html .= '
                        </div>';
                }
            }

            $html .= '
                    </div>';
        }

        $html .= '
                </div>

                <div class="footer">
                    <div class="validity-badge">✓ VALID PRESCRIPTIONS</div>
                    <div class="footer-content">
                        <div class="footer-item">
                            <div class="footer-item-label">Generated</div>
                            <div class="footer-item-value">' . (new \DateTime())->format('d/m/Y H:i') . '</div>
                        </div>
                        <div class="footer-item">
                            <div class="footer-item-label">Total Medications</div>
                            <div class="footer-item-value">' . $ordonnances->count() . '</div>
                        </div>
                        <div class="footer-item">
                            <div class="footer-item-label">Validity</div>
                            <div class="footer-item-value">1 Year</div>
                        </div>
                    </div>
                    <div class="legal-text">
                        This document contains all official medical prescriptions issued during the consultation. All medications must be presented to a licensed pharmacy for dispensing. Please consult your healthcare provider if you have any questions about your medications.
                    </div>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }
}
