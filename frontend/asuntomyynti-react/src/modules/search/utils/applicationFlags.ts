import { Apartment, ApartmentStateOfSale, OwnershipType } from '../../../types/common';

export type ApplicationCtaVariant = 'apply' | 'after-apply' | 'make-reservation';

export type ApplicationFlags = {
  isApartmentFree: boolean;
  isApplicationPeriodActive: boolean;
  applicationPeriodHasEnded: boolean;
  canApplyAfterwards: boolean;
  canCreateApplication: boolean;
  canMakeReservation: boolean;
  /** CTA label variant when a primary apply/reservation button should show. */
  ctaVariant: ApplicationCtaVariant | null;
  contactUrl: string;
  applicationUrl: string;
  ownershipType: OwnershipType;
};

/**
 * Resolve ownership from the apartment document, falling back to the project prop.
 */
const resolveOwnershipType = (apartment: Apartment, projectOwnershipIsHaso: boolean): OwnershipType => {
  const ownershipFromApartment = apartment.project_ownership_type?.toLowerCase();
  if (ownershipFromApartment === OwnershipType.haso) {
    return OwnershipType.haso;
  }
  if (ownershipFromApartment === OwnershipType.puolihitas) {
    return OwnershipType.puolihitas;
  }
  if (ownershipFromApartment === OwnershipType.hitas) {
    return OwnershipType.hitas;
  }
  return projectOwnershipIsHaso ? OwnershipType.haso : OwnershipType.hitas;
};

/**
 * Resolve application / reservation CTA flags from indexed apartment fields.
 *
 * Late reservation (HITAS/puolihitas): period ended, can_apply_afterwards,
 * FREE_FOR_RESERVATIONS → SEARCH:make-reservation ("Tee varaus").
 *
 * Late application (HASO only): period ended, can_apply_afterwards →
 * SEARCH:after-apply ("Luo jälkihakemus"). Never use that label for HITAS.
 */
export const computeApplicationFlags = (
  apartment: Apartment,
  userHasReservedOrSoldApartmentInProject: boolean,
  projectOwnershipIsHaso: boolean
): ApplicationFlags => {
  const stateOfSale = (apartment.apartment_state_of_sale || '').toUpperCase();
  const isApartmentFree = stateOfSale === ApartmentStateOfSale.FREE_FOR_RESERVATIONS.valueOf();
  const isApartmentReserved =
    stateOfSale === ApartmentStateOfSale.RESERVED.valueOf() ||
    stateOfSale === ApartmentStateOfSale.RESERVED_HASO.valueOf();
  const isApartmentSold = stateOfSale === ApartmentStateOfSale.SOLD.valueOf();

  const now = new Date().getTime();
  const applicationStartTime = apartment.project_application_start_time
    ? new Date(apartment.project_application_start_time).getTime()
    : undefined;
  const applicationEndTime = apartment.project_application_end_time
    ? new Date(apartment.project_application_end_time).getTime()
    : undefined;

  const isApplicationPeriodActive =
    applicationStartTime !== undefined &&
    applicationEndTime !== undefined &&
    now >= applicationStartTime &&
    now <= applicationEndTime;

  // Late CTAs only when the period has ended — never before it starts or when
  // dates are missing.
  const applicationPeriodHasEnded = applicationEndTime !== undefined && now > applicationEndTime;

  const canApplyAfterwards = apartment.project_can_apply_afterwards;
  const ownershipType = resolveOwnershipType(apartment, projectOwnershipIsHaso);
  const isHaso = ownershipType === OwnershipType.haso;

  const applicationUrl = `${window.location.origin}/application/add/${ownershipType}/${apartment.project_id}`;

  const contactUrl = `${window.location.origin}/contact/apply_for_free_apartment?apartment=${apartment.apartment_number}&project=${apartment.project_id}`;

  // HITAS/puolihitas post-period reservation: period ended, can apply afterwards,
  // apartment still free for reservations.
  const canMakeReservation =
    !isHaso &&
    applicationPeriodHasEnded &&
    !!canApplyAfterwards &&
    isApartmentFree &&
    !userHasReservedOrSoldApartmentInProject;

  // Regular apply during the period, or HASO late application after the period.
  // HITAS after-period uses canMakeReservation only — never after-apply.
  const canCreateApplication =
    !canMakeReservation &&
    !userHasReservedOrSoldApartmentInProject &&
    !isApartmentReserved &&
    !isApartmentSold &&
    ((isApplicationPeriodActive && !isApartmentFree) || (isHaso && applicationPeriodHasEnded && !!canApplyAfterwards));

  let ctaVariant: ApplicationCtaVariant | null = null;
  if (canMakeReservation) {
    ctaVariant = 'make-reservation';
  } else if (canCreateApplication) {
    // after-apply is HASO-only; HITAS must never get this label.
    ctaVariant = isHaso && applicationPeriodHasEnded && canApplyAfterwards ? 'after-apply' : 'apply';
  }

  return {
    isApartmentFree,
    isApplicationPeriodActive,
    applicationPeriodHasEnded,
    canApplyAfterwards,
    canCreateApplication,
    canMakeReservation,
    ctaVariant,
    contactUrl,
    applicationUrl,
    ownershipType,
  };
};
