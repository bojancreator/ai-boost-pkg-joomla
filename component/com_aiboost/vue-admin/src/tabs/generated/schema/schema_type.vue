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
        <option value="LocalBusiness">LocalBusiness</option>
        <option value="FoodEstablishment">Restaurant / Cafe</option>
        <option value="Restaurant">Restaurant (specific)</option>
        <option value="EducationalOrganization">School / University</option>
        <option value="LodgingBusiness">Hotel / Accommodation</option>
        <option value="MedicalClinic">Medical Clinic</option>
        <option value="LegalService">Lawyer / Law Firm</option>
        <option value="SportsActivityLocation">Gym / Sports Club</option>
        <option value="Dentist">Dentist</option>
        <option value="RealEstateAgent">Real Estate Agency</option>
        <option value="AutomotiveBusiness">Automotive Business</option>
        <option value="Store">Store / Shop</option>
        <option value="TouristAttraction">Tourist Attraction</option>
        <option value="ProfessionalService">Professional Service</option>
        <option value="Person">Person / Portfolio</option>
        <option value="NewsMediaOrganization">News / Media</option>
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
