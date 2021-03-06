<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\Reservation;
use App\Form\ConsultationFormType;
use App\Repository\ConsultationRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/consultation")
 * @IsGranted("ROLE_ADMIN")
 */
class ConsultationController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="consultations")
     */
    public function index(ConsultationRepository $consultationRepository): Response
    {
        $consultations = $consultationRepository->findActiveConsultations($this->getUser()->getId());

        return $this->render('consultation/index.html.twig', ['consultations' => $consultations]);
    }

    /**
     * @Route("/new", methods={"GET", "POST"}, name="add_consultation")
     */
    public function new(Request $request): Response
    {
        $consultation = new Consultation();
        $consultation->setAuthor($this->getUser());

        $form = $this->createForm(ConsultationFormType::class, $consultation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $datesValidation = $data->areDatesValid($data->getStartDate(), $data->getEndDate());

            if (!$datesValidation) {
                $this->addFlash('error', 'Data zakończenia musi być większa od daty rozpoczęcia!');
                return $this->redirectToRoute('add_consultation');
            } else if ($data->getStartDate() < new \DateTime()) {
                $this->addFlash('error', 'Data rozpoczęcia musi być większa od obecnej daty i godziny!');
                return $this->redirectToRoute('add_consultation');
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($consultation);
            $em->flush();

            $this->addFlash('success', 'Konsultacja została utworzona.');

            if ($request->request->has('submit')) {
                return $this->redirectToRoute('add_consultation');
            }

            return $this->redirectToRoute('consultations');
        }

        return $this->render('consultation/new.html.twig', [
            'consultation' => $consultation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id<\d+>}/show", methods={"GET"}, name="show_consultation")
     */
    public function show(Consultation $consultation): Response
    {
        return $this->render('consultation/show.html.twig', [
            'consultation' => $consultation,
        ]);
    }

    /**
     * @Route("/{id<\d+>}/edit",methods={"GET", "POST"}, name="edit_consultation")
     */
    public function edit(Request $request, Consultation $consultation, ReservationRepository $reservationRepository): Response
    {
        $form = $this->createForm(ConsultationFormType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $datesValidation = $data->areDatesValid($data->getStartDate(), $data->getEndDate());

            if (!$datesValidation) {
                $this->addFlash('error', 'Data zakończenia musi być większa od daty rozpoczęcia!');
                return $this->redirectToRoute('edit_consultation', ['id' => $consultation->getId()]);
            } else if ($data->getStartDate() < new \DateTime()) {
                $this->addFlash('error', 'Data rozpoczęcia musi być większa od obecnej daty i godziny!');
                return $this->redirectToRoute('add_consultation');
            }

            $em = $this->getDoctrine()->getManager();

            $termsToRemove = array_diff($consultation->getReservations()->map(function ($lol) {
                return $lol->getTerm();
            })->toArray(), array_values($consultation->getAllAvailableOptions()));

            foreach ($termsToRemove as $term) {
                $reservation = $reservationRepository->findByTermAndId($term, $consultation->getId());
                $em->remove($reservation);
            }
            $em->flush();

            $this->addFlash('success', 'Konsultacja została zaktualizowana.');

            return $this->redirectToRoute('edit_consultation', ['id' => $consultation->getId()]);
        }

        return $this->render('consultation/edit.html.twig', [
            'consultation' => $consultation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id<\d+>}/delete", methods={"GET"}, name="delete_consultation")
     */
    public function delete(Consultation $consultation): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($consultation);
        $em->flush();

        $this->addFlash('success', 'Konsultacja została usunięta.');

        return $this->redirectToRoute('consultations');
    }

    /**
     * @Route("/reservation/{id<\d+>}/delete", methods={"GET"}, name="delete_consultation_reservation")
     */
    public function deleteReservation(Reservation $reservation): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Rezerwacja została anulowana.');

        return $this->redirectToRoute('show_consultation', ['id' => $reservation->getConsultation()->getId()]);
    }
}
