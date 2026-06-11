<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=schema_type)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-schema-type">Schema Type</label>
      <select v-model="s.schema_type" data-ab-field="schema_type"
              class="ab-select" id="ab-gen-schema-type">
        <option value="Organization">Organization (generic)</option>
        <option value="LocalBusiness">Local Business (generic)</option>
        <option value="NewsMediaOrganization">News / Media</option>
        <option value="Restaurant">Restaurant</option>
        <option value="CafeOrCoffeeShop">Cafe / Coffee Shop</option>
        <option value="Bakery">Bakery</option>
        <option value="BarOrPub">Bar / Pub</option>
        <option value="FoodEstablishment">Food Establishment (generic)</option>
        <option value="MedicalClinic">Medical Clinic</option>
        <option value="Dentist">Dentist</option>
        <option value="Physician">Physician / Doctor</option>
        <option value="Pharmacy">Pharmacy</option>
        <option value="Hospital">Hospital</option>
        <option value="VeterinaryCare">Veterinary Care</option>
        <option value="LodgingBusiness">Hotel / Accommodation</option>
        <option value="BedAndBreakfast">Bed &amp; Breakfast</option>
        <option value="Resort">Resort</option>
        <option value="TouristAttraction">Tourist Attraction</option>
        <option value="BeautySalon">Beauty Salon</option>
        <option value="HairSalon">Hair Salon</option>
        <option value="NailSalon">Nail Salon</option>
        <option value="DaySpa">Day Spa</option>
        <option value="HealthClub">Gym / Health Club</option>
        <option value="SportsActivityLocation">Sports / Activity Location</option>
        <option value="ProfessionalService">Professional Service (generic)</option>
        <option value="LegalService">Lawyer / Law Firm</option>
        <option value="AccountingService">Accounting Service</option>
        <option value="RealEstateAgent">Real Estate Agency</option>
        <option value="Store">Store / Shop</option>
        <option value="AutomotiveBusiness">Automotive Business</option>
        <option value="EducationalOrganization">School / University</option>
        <option value="ChildCare">Childcare / Preschool</option>
        <option value="BankOrCreditUnion">Bank / Credit Union</option>
        <option value="FinancialService">Financial Service</option>
        <option value="InsuranceAgency">Insurance Agency</option>
        <option value="Person">Person / Portfolio</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSchemaType',
  props: { s: { type: Object, required: true } },
}
</script>
