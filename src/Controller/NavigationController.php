<?php

namespace App\Controller;

use DateTime;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use App\Entity\Facture;
use App\Entity\Services;
use App\Form\AccountType;
use App\Entity\Subscription;
use App\Form\SubscriptionType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Repository\FactureRepository;
use App\Repository\PricingRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class NavigationController extends Controller
{
  /**
     * @Route("/home", name="home")
     */
    public function home(ServicesRepository $serviceRepo){
        $user = $this->getUser();
        $vps = array();
        $serveur = array();
        $bureauVirtuel = array();
        $bdd = array();
        $allSub = array();
        $subscriptions = $user->getSubscriptions();
        for($i=0; $i<count($subscriptions);$i++){
            $id_serv = $subscriptions[$i]->getIdServices();
            $serv = $serviceRepo->findOneBy(['id' => $id_serv]);
            if($serv->getServiceType() == "vps"){
                $countVps = count($vps);
                $vps[$countVps] = ['name' => $subscriptions[$i]->getSubName(),'id' => $subscriptions[$i]->getId()];
                
            }
            else if($serv->getServiceType() == "srv"){
                $countServ = count($serveur);
                $serveur[$countServ] = ['name' => $subscriptions[$i]->getSubName(),'id' => $subscriptions[$i]->getId()];
            }
            else if($serv->getServiceType() == "vdi"){
                $countVdi = count($bureauVirtuel);
                $bureauVirtuel[$countVdi] = ['name' => $subscriptions[$i]->getSubName(),'id' => $subscriptions[$i]->getId()];
            }
            else if($serv->getServiceType() == "bdd"){
                $countBdd = count($bdd);
                $bdd[$countBdd] = ['name' => $subscriptions[$i]->getSubName(),'id' => $subscriptions[$i]->getId()];
            }
        }
           
        return $this->render('base.html.twig' , ['vps' => $vps , 'serveur' => $serveur, 'bureauVirtuel' => $bureauVirtuel,'bdd' => $bdd]);
    }
  

     /**
     * @Route("/intro", name="intro")
     */
    public function intro(Request $request, ServicesRepository $serviceRepo){
        $user = $this->getUser();
        $allSub = array();
        $subscriptions = $user->getSubscriptions();
        for($i=0; $i<count($subscriptions);$i++){
            $id_serv = $subscriptions[$i]->getIdServices();
            $serv = $serviceRepo->findOneBy(['id' => $id_serv]);
            $allSub[$i] = ['name' => $subscriptions[$i]->getSubName(), 'id' => $subscriptions[$i]->getId(), 'status' => $subscriptions[$i]->getStatus(),'headline' => $serv->getHeadline(), 'type' => $serv->getServiceType()];
        }
        $id = $request->request->get('id');

        //fausses données 
        $metrics = array (['CPU' => '24', 'RAM' => '32','Disque'=>'15','id' => 1]);
        
        return $this->render('pages/home.html.twig', ['service'=>$metrics, 'souscription' => $allSub]);
    }     
    /**
     * @Route("/formSub", name="formSub")
     * 
     * controller pour la page serveur qui va afficher soit un formulaire soit une liste des services possibles
     */
    public function formSub(Request $request,ServicesRepository $repo,EntityManagerInterface $em, PricingRepository $repoPrice, SubscriptionRepository $repoSub){
        //info contient le type de service choisi (vps/vdi/srv/bdd) et on cherche dans la bdd ce service disponible
        $info = $request->request->get('serv');
        if($info == "srv"){
            $choix = "Serveur";
        }
        else if($info =="bdd"){
            $choix = "Base de donnée";
        }
        else if($info =="vdi"){
            $choix = "Bureau virtuel";
        }
        else if($info =="vps"){
            $choix = "VPS";
        }
        
        $vps = $repo->findBy(['service_type' => $info, 'available' => '1']);
        $price = $repoPrice->findAll();
        //on set different objet utile
        $date = new DateTime;
        $sub = new Subscription;
        $user = new User;
        //on recupere les infos de l'user courant
        $user = $this->getUser();
        //création du formulaire de souscription, avec le parametre action qui indique le controller dans lequel le form sera traité
        $form = $this->createForm(SubscriptionType::class, $sub, 
        ['action' => $this->generateUrl('formSub')]);
        $form->handleRequest($request);
        //si le form est valide est soumis
        if ($form->isSubmitted() && $form->isValid()) {
            //le status est set a 0 par défaut, l'admin pourra le set a 1 pour activer, set la date de debut, l'adresse ip, l'id user et le nom de la souscription
            $sub->setStatus('0');
            $sub->setDateSub($date);
            $sub->setIpAdresse('231');
            $sub->setIdUser($user);
            //recuperation du prix
            $prixService = $request->request->get('prixService');
            $sub->setPrice($prixService);
            //recuperation et instanciation du service choisis
            $serveur = $request->request->get('serveur');
            $vpsChosen = $repo->findOneBy(['id' => $serveur]);
            $sub->setIdServices($vpsChosen);
            //on crée le nom de la souscription
            $allSub = $repoSub->findAll();
            $subId = $allSub[count($allSub) -1]->getId() +1;
            $sub_name = 'CL12'. $user->getId() . $vpsChosen->getServiceType() . $subId;
            $sub->setSubName($sub_name);
            //envoie en bdd et redirection vers home
            $em->persist($sub);
            $em->flush();
            return $this->redirectToRoute('home');
        }
        return $this->render('pages/formSub.html.twig', [
            'subscriptionForm' => $form->createView(), 'liste' => $vps, 'choix' => $choix,'price' => $price
        ]);

    }
         /**
     * @Route("/info", name="info")
     */
    public function info(Request $request, SubscriptionRepository $sub, ServicesRepository $serviceRepo){
        //recupération de l'id du service vps
        $id = $request->request->get('id');
        $info = $sub->findOneBy(['id' => $id]);
        $protection ="";
        $replication = "";
        $retention = array('Sauvegarde du 17/11/2019');
        $serv = $serviceRepo->findOneBy(['id' => $info->getIdServices()]);
        if($info->getHighAvailability()){
        $arr = explode(',',$info->getHighAvailability());
            if(count($arr)==2){
                $protection = $arr[0];
                $replication = $arr[1];
            }
            else if (count($arr) == 1){
                if($arr[0] == "protectionHA"){
                    $protection = $arr[0];
                }
                else if ($arr[0] == "replicationServ"){
                    $replication = $arr[0];
                }
            } 
            
        }

        $result = array(['headline' => $serv->getHeadline(), 'name' => $info->getSubName(),'ip' => $info->getIP(),'protection' => $protection,'replication' => $replication,'status' => $info->getStatus(),'backup' => $info->getBackup(),'cpu'=> $info->getCpu(),'ram' => $info->getRam(),'space'=> $info->getDiskSpace() ]);
        
        return $this->render('pages/info.html.twig',['info' => $result,'retention'=>$retention]);
    }
         /**
     * @Route("/infometrics", name="infometrics")
     */
    public function infoMetrics(){

        return $this->render('pages/infoMetrics.html.twig');
    }
        /**
     * @Route("/forfait", name="forfait")
     */
    public function forfait(Request $request){
        $info = $request->request->get('forfait');
        $serveur = $this->getDoctrine()->getRepository(Services::class);
        $description = $serveur->findBy(['id' => $info]);
        return $this->render('pages/forfait.html.twig',['info' => $description]);
    }

    /**
     * @Route("/infofacture", name="infofacture")
     */
    public function infofacture(FactureRepository $factrepo,SubscriptionRepository $subrepo){
        $user = New user();
        $totalPrice = 0;
        $user = $this->getUser();
        $userFacture= $factrepo->findBy(['id_user'=>$user->getId()],['date_edition'=>"ASC"]);
       
        $listPrice = $subrepo->findBy(['id_user'=>$user->getId()],['price'=>"ASC"]);
        foreach ($listPrice as $price){
                    $totalPrice = $totalPrice + $price->getPrice(); 
                }

        return $this->render('pages/infoFacture.html.twig',
        ['userFacture'=>$userFacture,'totalPrice'=>$totalPrice]);
    }

    /**
     * @Route("/facture", name="facture")
     */
    public function facture(SubscriptionRepository $subrepo,FactureRepository $factrepo,Request $request){
        // On instancie tout ce qu'on a besoin
        $user = new User();
        $dateMois = date('m');
        $dateAnnee = date('Y');
        $date = new DateTime();
        $facture = new Facture();
        $totalPrice = 0;
        //On défini nos variables
        $user=$this->getUser();
        $subscriptions = $user->getSubscriptions();
        $dateEdition = new DateTime('1'.'-'.$dateMois.'-'.$dateAnnee);
        $dateEcheance= new DateTime('5'.'-'.$dateMois.'-'.$dateAnnee);    
        // On récupere les données grace à doctrine
        $userSub= $subrepo->findBy(['id_user'=>$user->getId()],['date_sub'=>"ASC"]);
        $userFacture= $factrepo->findBy(['id_user'=>$user->getId()],['date_edition'=>"ASC"]);
        //Calcule pour le total des prix
        $listPrice = $subrepo->findBy(['id_user'=>$user->getId()],['price'=>"ASC"]);
        foreach ($listPrice as $price){
                    $totalPrice = $totalPrice + $price->getPrice();
                }
        // On défini chaque setter 
        $facture->setDateEcheance($dateEcheance);
        $facture->setDateEdition($dateEdition);
        $facture->setIdUser($user);
        $facture->setEtat("Payé");
        $facture->setPrice($totalPrice);
        $facture->setNumeroFacture($user->getId().$dateEdition->format('d-m-Y').$facture->getId());


        // Configure Dompdf avec ce qu'on à besoin
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->setDpi(150);
        // On instancie la librairie 
        $dompdf = new Dompdf($pdfOptions);
        
        // La page ou la facturation ce trouve 
        $html = $this->renderView('pages/facture.html.twig',['userSub'=>$userSub,'factures'=>$userFacture,'totalPrice'=>$totalPrice]);
        
        // Charge l'HTML avec la library
        $dompdf->loadHtml($html);
        
        // (Optional) Choisir la taille de la feuille + mode portrait ou paysage
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le HMTL en PDF
        $dompdf->render();

        // Genere le PDF et le télécharge avec la date du jours
        $dompdf->stream($user->getId().$date->format('d-m-Y'), [
            "Attachment" => true, 
        ]);
    }

    /**
     * @Route("/mon-compte/profil", name="profil")
     */
    public function profil(Request $request, UserRepository $user,EntityManagerInterface $em)
    {
        $user = $this->getUser(); // Utilisateur courant
        $form = $this->createForm(AccountType::class, $user,['action' => $this->generateUrl('profil')]); // Créer un formulaire
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { // Si le formulaire est valide et qu'il est envoyé
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success','Votre profil a bien été modifié');
            return $this->redirectToRoute('home');
        }

        return $this->render('pages/monProfil.html.twig', [
            'formProfil'=> $form->createView(),
        ]);}
}