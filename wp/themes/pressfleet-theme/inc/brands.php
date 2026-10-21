<?php
/**
 * Starting copy for the three demo clinics. The patterns read it once, when a section is inserted; after that the
 * texts belong to the site and are edited in the Site Editor.
 *
 * The brand is chosen with the theme mod "pressfleet_brand" (infra/provision.sh sets it next to the matching style
 * variation).
 *
 * @package PressFleet\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Copy of the current site's brand.
 *
 * @return array<string, mixed>
 */
function pressfleet_theme_brand(): array {
	$brands = pressfleet_theme_brands();
	$slug   = (string) get_theme_mod( 'pressfleet_brand', 'alder' );
	return $brands[ $slug ] ?? $brands['alder'];
}

/**
 * All brands.
 *
 * @return array<string, array<string, mixed>>
 */
function pressfleet_theme_brands(): array {
	return array(
		'alder' => array(
			'eyebrow'     => __( 'Physiotherapy in Lisbon', 'pressfleet-theme' ),
			'title'       => __( 'Move without pain again.', 'pressfleet-theme' ),
			'lead'        => __( 'Hands-on treatment and a plan you can follow at home, for back and neck pain, sports injuries and recovery after surgery.', 'pressfleet-theme' ),
			'cta'         => __( 'Book a first visit', 'pressfleet-theme' ),
			'rating'      => array( '4.9', __( 'average rating from 1,200 patients', 'pressfleet-theme' ) ),
			'stats'       => array(
				array( __( '12 years', 'pressfleet-theme' ), __( 'helping people move', 'pressfleet-theme' ) ),
				array( __( '6 therapists', 'pressfleet-theme' ), __( 'all fully licensed', 'pressfleet-theme' ) ),
				array( __( '48 hours', 'pressfleet-theme' ), __( 'to your first visit', 'pressfleet-theme' ) ),
				array( __( '3 clinics', 'pressfleet-theme' ), __( 'in the same network', 'pressfleet-theme' ) ),
			),
			'services'    => array(
				array( __( 'Back and neck pain', 'pressfleet-theme' ), __( 'Manual therapy and exercises that ease pain and keep it from coming back.', 'pressfleet-theme' ) ),
				array( __( 'Sports injuries', 'pressfleet-theme' ), __( 'From sprains to strains, a step-by-step return to the sport you love.', 'pressfleet-theme' ) ),
				array( __( 'After surgery', 'pressfleet-theme' ), __( 'Rebuild strength and range of motion with a plan agreed with your surgeon.', 'pressfleet-theme' ) ),
			),
			'cases_title' => __( 'Patients who got moving again', 'pressfleet-theme' ),
			'journal'     => __( 'Advice from our therapists', 'pressfleet-theme' ),
			'quotes'      => array(
				array( __( 'After two years of back pain I finally have a routine that works, and I understand why.', 'pressfleet-theme' ), __( 'Marta, office manager', 'pressfleet-theme' ) ),
				array( __( 'They got me back on the pitch three weeks earlier than I expected, without cutting corners.', 'pressfleet-theme' ), __( 'João, amateur footballer', 'pressfleet-theme' ) ),
			),
			'faq'         => array(
				array( __( 'Do I need a referral?', 'pressfleet-theme' ), __( 'No. You can book directly; bring any scans or notes you already have.', 'pressfleet-theme' ) ),
				array( __( 'How long is a session?', 'pressfleet-theme' ), __( 'The first assessment takes 60 minutes, follow-up sessions 45 minutes.', 'pressfleet-theme' ) ),
				array( __( 'What should I wear?', 'pressfleet-theme' ), __( 'Comfortable clothes you can move in. We have changing rooms.', 'pressfleet-theme' ) ),
			),
			'address'     => __( 'Rua das Flores 12, Lisbon · Mon–Fri 8:00–20:00', 'pressfleet-theme' ),
		),
		'birch' => array(
			'eyebrow'     => __( 'Family dentistry in Porto', 'pressfleet-theme' ),
			'title'       => __( 'A calm visit, a healthy smile.', 'pressfleet-theme' ),
			'lead'        => __( 'Check-ups, cleaning and gentle treatment for the whole family, explained in plain words and planned around your day.', 'pressfleet-theme' ),
			'cta'         => __( 'Book a check-up', 'pressfleet-theme' ),
			'rating'      => array( '4.8', __( 'average rating from 950 patients', 'pressfleet-theme' ) ),
			'stats'       => array(
				array( __( '15 years', 'pressfleet-theme' ), __( 'caring for smiles', 'pressfleet-theme' ) ),
				array( __( '5 dentists', 'pressfleet-theme' ), __( 'and 2 hygienists', 'pressfleet-theme' ) ),
				array( __( 'Same week', 'pressfleet-theme' ), __( 'appointments', 'pressfleet-theme' ) ),
				array( __( '3 clinics', 'pressfleet-theme' ), __( 'in the same network', 'pressfleet-theme' ) ),
			),
			'services'    => array(
				array( __( 'Check-ups and cleaning', 'pressfleet-theme' ), __( 'A thorough look and a professional clean, twice a year.', 'pressfleet-theme' ) ),
				array( __( 'Children’s dentistry', 'pressfleet-theme' ), __( 'Friendly first visits that make kids look forward to the next one.', 'pressfleet-theme' ) ),
				array( __( 'Fillings and crowns', 'pressfleet-theme' ), __( 'Durable, natural-looking repairs, with numbing that really works.', 'pressfleet-theme' ) ),
			),
			'cases_title' => __( 'Smiles we are proud of', 'pressfleet-theme' ),
			'journal'     => __( 'Tips from our dentists', 'pressfleet-theme' ),
			'quotes'      => array(
				array( __( 'The first dentist my son was not afraid of. He asks when we are going back.', 'pressfleet-theme' ), __( 'Inês, mother of two', 'pressfleet-theme' ) ),
				array( __( 'Everything was explained before it happened, and nothing hurt. That is new for me.', 'pressfleet-theme' ), __( 'Rui, teacher', 'pressfleet-theme' ) ),
			),
			'faq'         => array(
				array( __( 'How often should I come?', 'pressfleet-theme' ), __( 'Most people need a check-up and clean every six months.', 'pressfleet-theme' ) ),
				array( __( 'Do you see children?', 'pressfleet-theme' ), __( 'Yes, from their first tooth. Short, playful visits help a lot.', 'pressfleet-theme' ) ),
				array( __( 'What if I am nervous?', 'pressfleet-theme' ), __( 'Tell us when you book. We take it slowly and agree on a stop signal.', 'pressfleet-theme' ) ),
			),
			'address'     => __( 'Avenida da Boavista 88, Porto · Mon–Sat 9:00–19:00', 'pressfleet-theme' ),
		),
		'cedar' => array(
			'eyebrow'     => __( 'Eye care in Coimbra', 'pressfleet-theme' ),
			'title'       => __( 'See clearly, every day.', 'pressfleet-theme' ),
			'lead'        => __( 'Eye exams, glasses and contact lenses fitted by optometrists who take the time to get it right.', 'pressfleet-theme' ),
			'cta'         => __( 'Book an eye exam', 'pressfleet-theme' ),
			'rating'      => array( '4.9', __( 'average rating from 800 patients', 'pressfleet-theme' ) ),
			'stats'       => array(
				array( __( '20 years', 'pressfleet-theme' ), __( 'of eye care', 'pressfleet-theme' ) ),
				array( __( '4 optometrists', 'pressfleet-theme' ), __( 'with modern equipment', 'pressfleet-theme' ) ),
				array( __( '45 minutes', 'pressfleet-theme' ), __( 'for a full exam', 'pressfleet-theme' ) ),
				array( __( '3 clinics', 'pressfleet-theme' ), __( 'in the same network', 'pressfleet-theme' ) ),
			),
			'services'    => array(
				array( __( 'Eye exams', 'pressfleet-theme' ), __( 'A complete check of your vision and eye health, for all ages.', 'pressfleet-theme' ) ),
				array( __( 'Glasses', 'pressfleet-theme' ), __( 'Frames that suit you and lenses made for how you use your eyes.', 'pressfleet-theme' ) ),
				array( __( 'Contact lenses', 'pressfleet-theme' ), __( 'Fitting, a trial period and a routine that keeps your eyes comfortable.', 'pressfleet-theme' ) ),
			),
			'cases_title' => __( 'Stories from our patients', 'pressfleet-theme' ),
			'journal'     => __( 'Notes from our optometrists', 'pressfleet-theme' ),
			'quotes'      => array(
				array( __( 'I did not know how much I was missing until I tried my new glasses on in the shop.', 'pressfleet-theme' ), __( 'Ana, architect', 'pressfleet-theme' ) ),
				array( __( 'They found the reason for my headaches in one visit: too many hours at the screen.', 'pressfleet-theme' ), __( 'Tiago, developer', 'pressfleet-theme' ) ),
			),
			'faq'         => array(
				array( __( 'How often should I have an eye exam?', 'pressfleet-theme' ), __( 'Every two years for most adults, every year for children and over-60s.', 'pressfleet-theme' ) ),
				array( __( 'Can I try contact lenses first?', 'pressfleet-theme' ), __( 'Yes. Every fitting includes a free trial pair.', 'pressfleet-theme' ) ),
				array( __( 'Do you check children’s eyes?', 'pressfleet-theme' ), __( 'Yes, from age three, with tests made for kids.', 'pressfleet-theme' ) ),
			),
			'address'     => __( 'Rua Ferreira Borges 40, Coimbra · Mon–Fri 9:30–19:00', 'pressfleet-theme' ),
		),
	);
}
