<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\Reservation;
use App\Form\ReservationFormType;
use App\Repository\ConsultationRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/reservation")
 * @IsGranted("ROLE_USER")
 */
class ReservationController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="reservations")
     */
    public function index(ConsultationRepository $consultationRepository): Response
    {
        $consultations = $consultationRepository->findBy(['status' => true], ['startDate' => 'ASC']);

        $filtered = [];
        foreach ($consultations as $consultation) {
            if ($consultation->canBeListed()) {
                $filtered[] = $consultation;
            }
        }

        return $this->render('reservation/index.html.twig', ['consultations' => $filtered]);
    }

    /**
     * @Route("/{id<\d+>}/new", methods={"GET", "POST"}, name="add_reservation")
     */
    public function new(Request $request, Consultation $consultation): Response
    {
        $reservation = new Reservation();
        $reservation->setConsultation($consultation);
        $reservation->setAuthor($this->getUser());

        $form = $this->createForm(ReservationFormType::class, $reservation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', 'Rezerwacja została utworzona.');

            if ($request->request->has('submit')) {
                return $this->redirectToRoute('add_reservation');
            }

            return $this->redirectToRoute('reservations');
        }

        return $this->render('reservation/new.html.twig', [
            'consultation' => $consultation,
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id<\d+>}/show", methods={"GET"}, name="show_reservation")
     */
    public function show(Consultation $consultation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'consultation' => $consultation
        ]);
    }

    /**
     * @Route("/personal",methods={"GET"}, name="personal_reservations")
     */
    public function personal(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser()->getId();
        $reservations = $reservationRepository->findBy(['author' => $user], ['createdAt' => 'ASC']);

        return $this->render('reservation/personal.html.twig', [
            'reservations' => $reservations
        ]);
    }

    /**
     * @Route("/personal/{id<\d+>}/delete", methods={"GET"}, name="delete_reservation")
     */
    public function delete(Reservation $reservation): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Rezerwacja została anulowana.');

        return $this->redirectToRoute('personal_reservations');
    }
}
