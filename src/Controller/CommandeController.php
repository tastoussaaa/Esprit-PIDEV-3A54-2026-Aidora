<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commande')]
class CommandeController extends AbstractController
{
    #[Route('/', name: 'commande_index', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        
        // Get filter and sort parameters from URL
        $statut = $request->query->get('statut', '');
        $tri = $request->query->get('tri', 'dateCommande');
        $ordre = $request->query->get('ordre', 'DESC');
        $recherche = $request->query->get('recherche', '');
        
        // Validate tri and ordre parameters
        $tri = in_array($tri, ['dateCommande', 'montantTotal', 'statut']) ? $tri : 'dateCommande';
        $ordre = in_array($ordre, ['ASC', 'DESC']) ? $ordre : 'DESC';
        
        // Fetch commandes with filters
        $commandes = $user
            ? $commandeRepo->findForUser($user, $statut ?: null, $tri, $ordre)
            : $commandeRepo->findBy([], [$tri => $ordre]);
        
        // Search filter (on product name)
        if ($recherche !== null && $recherche !== '') {
            $recherche = trim($recherche);
            $commandes = array_filter($commandes, function ($c) use ($recherche) {
                return stripos($c->getProduit() ? $c->getProduit()->getNom() : '', $recherche) !== false;
            });
        }
        
        // Get all distinct statuts for filter dropdown
        $statuts = $commandeRepo->findDistinctStatuts();
        
        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
            'statut_filtre' => $statut,
            'statuts' => $statuts,
            'tri' => $tri,
            'ordre' => $ordre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'commande_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ProduitRepository $produitRepo): Response
    {
        $commande = new Commande();
        $produitId = $request->query->get('produitId');
        if ($produitId) {
            $produit = $produitRepo->find($produitId);
            if ($produit) {
                $commande->setProduit($produit);
                $commande->setQuantite(1);
            }
        }
        if ($this->getUser()) {
            $commande->setDemandeur($this->getUser());
        }

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit = $commande->getProduit();
            $quantite = $commande->getQuantite();
            
            // Check if enough stock available
            if ($produit->getStock() < $quantite) {
                $this->addFlash('error', 'Stock insuffisant. Stock disponible: ' . $produit->getStock());
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
                    ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
                    ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋'],
                ];
                return $this->render('commande/new.html.twig', [
                    'commande' => $commande,
                    'form' => $form->createView(),
                    'navigation' => $navigation,
                ]);
            }
            
            // Decrease stock
            $produit->setStock($produit->getStock() - $quantite);
            $commande->setMontantTotal($produit->getPrix() * $quantite);
            $em->persist($commande);
            $em->flush();
            $this->addFlash('success', 'Commande enregistrée.');
            return $this->redirectToRoute('commande_index');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋'],
        ];

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/{id}', name: 'commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}/edit', name: 'commande_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        // Restrict editing to within first 3 hours after order
        $dateCmd = $commande->getDateCommande();
        if ($dateCmd) {
            $now = new \DateTime();
            $seconds = $now->getTimestamp() - $dateCmd->getTimestamp();
            if ($seconds > 3 * 3600) {
                $this->addFlash('error', 'Modification impossible : délai de modification (3 heures) dépassé.');
                return $this->redirectToRoute('commande_index');
            }
        }

        // Store original quantity for stock adjustment
        $originalQuantite = $commande->getQuantite();
        
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit = $commande->getProduit();
            $newQuantite = $commande->getQuantite();
            $quantiteDifference = $newQuantite - $originalQuantite;
            
            // Check if enough stock available for the difference
            if ($quantiteDifference > 0 && $produit->getStock() < $quantiteDifference) {
                $this->addFlash('error', 'Stock insuffisant. Stock disponible: ' . $produit->getStock());
                return $this->render('commande/edit.html.twig', [
                    'commande' => $commande,
                    'form' => $form->createView(),
                ]);
            }
            
            // Adjust stock based on quantity change
            $produit->setStock($produit->getStock() - $quantiteDifference);
            $commande->setMontantTotal($produit->getPrix() * $newQuantite);
            $em->flush();
            $this->addFlash('success', 'Commande mise à jour.');
            return $this->redirectToRoute('commande_index');
        }

        return $this->render('commande/edit.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $commande->getId(), $token)) {
            // Allow deletion (cancellation) only within 24 hours of order
            $dateCmd = $commande->getDateCommande();
            if ($dateCmd) {
                $now = new \DateTime();
                $seconds = $now->getTimestamp() - $dateCmd->getTimestamp();
                if ($seconds > 24 * 3600) {
                    $this->addFlash('error', 'Annulation impossible : délai de 24 heures dépassé.');
                    return $this->redirectToRoute('commande_index');
                }
            }

            // Restore stock when command is deleted
            $produit = $commande->getProduit();
            $produit->setStock($produit->getStock() + $commande->getQuantite());

            $em->remove($commande);
            $em->flush();
            $this->addFlash('success', 'Commande supprimée.');
        }
        return $this->redirectToRoute('commande_index');
    }
}
