<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProduitController extends AbstractController
{
    #[Route('/produit', name: 'produit_list', methods: ['GET'])]
    public function list(Request $request, ProduitRepository $produitRepo): Response
    {
        // Get query parameters from the URL (e.g., /produit?categorie=Medicament&tri=prix)
        // These are the filters the user selected in the browser
        $categorie = $request->query->get('categorie', '');  // Filter by category if provided
        $tri = $request->query->get('tri', 'prix');            // Default to 'prix' instead of 'nom'
        $ordre = $request->query->get('ordre', 'ASC');        // Sort direction: ASC or DESC (default: ascending)
        $recherche = $request->query->get('recherche', '');   // Search text if user is searching for a product

        // Validate 'tri' and 'ordre' parameters
        $tri = in_array($tri, ['nom', 'prix', 'categorie']) ? $tri : 'prix'; // Default to 'prix' if invalid
        $ordre = in_array($ordre, ['ASC', 'DESC']) ? $ordre : 'ASC';

        // Fetch all products from the database, filtered by category and sorted according to user's selection
        // This queries the database and returns the product list
        $produits = $produitRepo->findForShop($categorie ?: null, $tri, $ordre);

        // If user typed something in the search box, filter the product list to only show matching products
        // Search in product name OR category
        if ($recherche !== null && $recherche !== '') {
            $recherche = trim($recherche);  // Remove extra spaces
            $produits = array_filter($produits, function ($p) use ($recherche) {
                return stripos($p->getNom(), $recherche) !== false
                    || stripos($p->getCategorie() ?? '', $recherche) !== false;
            });
        }

        // Get all available categories from the database
        // This populates the category dropdown filter in the browser
        $categories = $produitRepo->findDistinctCategories();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋'],
        ];

        // Load the shop/list template and send all the data to display
        // Users will see a table/grid of all products with filter dropdowns and search box
        return $this->render('produit/list.html.twig', [
            'produits' => $produits,           // All filtered products to display
            'categories' => $categories,       // List of categories for the filter dropdown
            'categorie_filtre' => $categorie,  // Currently selected category (to show what's active)
            'tri' => $tri,                     // Current sort field (to remember user's choice)
            'ordre' => $ordre,                 // Current sort direction (to remember user's choice)
            'recherche' => $recherche,         // Current search text (to show in search box)
            'navigation' => $navigation,
        ]);
    }

    #[Route('/produit/add', name: 'produit_add')]
    public function add(Request $request, EntityManagerInterface $em, ProduitRepository $produitRepo, SluggerInterface $slugger): Response
    {
        $produit = new Produit();

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $produit->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléversement de l\'image.');
                    return $this->redirectToRoute('produit_add');
                }
            }
            $em->persist($produit);
            $em->flush();

            $this->addFlash('success', 'Produit ajouté avec succès !');

            return $this->redirectToRoute('produit_add');
        }

        $produits = $produitRepo->findBy([], ['id' => 'DESC']);
        $categories = $produitRepo->findDistinctCategories();

        //
        return $this->render('produit/add.html.twig', [
            'form' => $form->createView(),
            'produits' => $produits,
            'categories' => $categories,
        ]);
    }






    #[Route('/produit/{id}', name: 'produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/produit/{id}/edit', name: 'produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $em, ProduitRepository $produitRepo, SluggerInterface $slugger): Response
    {
        // Create an empty form and bind it to the existing product data
        // When you first visit /produit/123/edit, the form fields will be pre-filled with that product's info
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        // Check if the user submitted the form (POST request) and all validation rules passed
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $produit->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléversement de l\'image.');
                    return $this->redirectToRoute('produit_edit', ['id' => $produit->getId()]);
                }
            }
            // Save the updated product to the database
            $em->flush();
            
            // Show a green success message at the top of the page
            $this->addFlash('success', 'Produit mis à jour.');
            
            // Redirect the user back to the add page after successful update
            return $this->redirectToRoute('produit_add');
        }

        // Fetch all available product categories from the database
        // This is used to populate dropdown menus in the form
        $categories = $produitRepo->findDistinctCategories();

        // Load the edit template and display the form with the current product data
        // Users will see text fields, dropdown menus, and a submit button in their browser
        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),  // The form is converted to HTML for display
            'produit' => $produit,          // The product being edited
            'categories' => $categories,    // Available categories for selection
        ]);
    }

    #[Route('/produit/{id}', name: 'produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $token)) {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }
        return $this->redirectToRoute('produit_add');
    }
}
