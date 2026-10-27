import { computeApplicationFlags } from './applicationFlags';
import { Apartment, OwnershipType } from '../../../types/common';

const inThePast = '2000-01-01T00:00:00.000Z';
const inTheFuture = '2100-01-01T00:00:00.000Z';

const baseApartment = {
  nid: 84,
  apartment_number: 'A15',
  project_id: 15,
  apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
  application_url: '',
  project_application_start_time: inThePast,
  project_application_end_time: inThePast,
  project_can_apply_afterwards: true,
  project_ownership_type: 'hitas',
} as Apartment;

describe('computeApplicationFlags', () => {
  describe('canMakeReservation', () => {
    test('is true for free HITAS apartment after period with project_can_apply_afterwards', () => {
      const flags = computeApplicationFlags(baseApartment, false, false);

      expect(flags.applicationPeriodHasEnded).toBe(true);
      expect(flags.canMakeReservation).toBe(true);
      expect(flags.canCreateApplication).toBe(false);
    });

    test('requires FREE_FOR_RESERVATIONS (allowlist), not merely non-reserved/sold', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
        },
        false,
        false
      );

      expect(flags.canMakeReservation).toBe(false);
    });

    test('is false when application period has not started yet', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          project_application_start_time: inTheFuture,
          project_application_end_time: inTheFuture,
        },
        false,
        false
      );

      expect(flags.isApplicationPeriodActive).toBe(false);
      expect(flags.applicationPeriodHasEnded).toBe(false);
      expect(flags.canMakeReservation).toBe(false);
    });

    test('is false when application dates are empty', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          project_application_start_time: '',
          project_application_end_time: '',
        },
        false,
        false
      );

      expect(flags.applicationPeriodHasEnded).toBe(false);
      expect(flags.canMakeReservation).toBe(false);
    });

    test('is false when project_can_apply_afterwards is false even if can_apply_afterwards is true', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          can_apply_afterwards: true,
          project_can_apply_afterwards: false,
        } as Apartment,
        false,
        false
      );

      expect(flags.canApplyAfterwards).toBe(false);
      expect(flags.canMakeReservation).toBe(false);
    });

    test('is false for HASO apartments', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          project_ownership_type: 'haso',
        },
        false,
        true
      );

      expect(flags.canMakeReservation).toBe(false);
      expect(flags.ctaVariant).toBe('after-apply');
    });

    test('never uses after-apply for HITAS even when can_apply_afterwards is true', () => {
      const flags = computeApplicationFlags(baseApartment, false, false);

      expect(flags.ctaVariant).toBe('make-reservation');
      expect(flags.ctaVariant).not.toBe('after-apply');
    });

    test('uses apartment project_ownership_type over the HASO project prop', () => {
      // Prop says HASO, but apartment document is HITAS — trust the apartment.
      const flags = computeApplicationFlags(baseApartment, false, true);

      expect(flags.ownershipType).toBe(OwnershipType.hitas);
      expect(flags.ctaVariant).toBe('make-reservation');
    });

    test('is false when user already has a reserved or sold apartment in the project', () => {
      const flags = computeApplicationFlags(baseApartment, true, false);

      expect(flags.canMakeReservation).toBe(false);
    });

    test('is false during an active application period', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          project_application_start_time: inThePast,
          project_application_end_time: inTheFuture,
        },
        false,
        false
      );

      expect(flags.isApplicationPeriodActive).toBe(true);
      expect(flags.applicationPeriodHasEnded).toBe(false);
      expect(flags.canMakeReservation).toBe(false);
    });
  });

  describe('applicationUrl', () => {
    test('builds URL from ownership and project_id when application_url is empty', () => {
      const flags = computeApplicationFlags(baseApartment, false, false);

      expect(flags.applicationUrl).toBe(`${window.location.origin}/application/add/hitas/15`);
      expect(flags.ownershipType).toBe(OwnershipType.hitas);
    });

    test('uses puolihitas ownership in the built URL', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          project_ownership_type: 'puolihitas',
        },
        false,
        false
      );

      expect(flags.ownershipType).toBe(OwnershipType.puolihitas);
      expect(flags.applicationUrl).toBe(`${window.location.origin}/application/add/puolihitas/15`);
    });

    test('ignores a stale application_url and builds from indexed fields', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          application_url: 'https://example.com/contact/apply_for_free_apartment',
        },
        false,
        false
      );

      expect(flags.applicationUrl).toBe(`${window.location.origin}/application/add/hitas/15`);
    });
  });

  describe('canCreateApplication', () => {
    test('is true for HASO after period with can_apply_afterwards', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
          project_ownership_type: 'haso',
          project_can_apply_afterwards: true,
        },
        false,
        true
      );

      expect(flags.canCreateApplication).toBe(true);
      expect(flags.canMakeReservation).toBe(false);
      expect(flags.ctaVariant).toBe('after-apply');
    });

    test('shows after-apply for free HASO apartments after period', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
          project_ownership_type: 'haso',
          project_can_apply_afterwards: true,
        },
        false,
        true
      );

      expect(flags.canCreateApplication).toBe(true);
      expect(flags.ctaVariant).toBe('after-apply');
    });

    test('is true during an active application period', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
          project_application_end_time: inTheFuture,
          project_can_apply_afterwards: false,
        },
        false,
        false
      );

      expect(flags.canCreateApplication).toBe(true);
      expect(flags.ctaVariant).toBe('apply');
    });

    test('does not show after-apply for HITAS open apartments after period', () => {
      const flags = computeApplicationFlags(
        {
          ...baseApartment,
          apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
          project_ownership_type: 'hitas',
          project_can_apply_afterwards: true,
        },
        false,
        false
      );

      expect(flags.canCreateApplication).toBe(false);
      expect(flags.canMakeReservation).toBe(false);
      expect(flags.ctaVariant).toBeNull();
    });
  });
});
