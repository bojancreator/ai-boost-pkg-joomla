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
        <option value="Organization">Organization (generic) [Free]</option>
        <option value="LocalBusiness">LocalBusiness [Free]</option>
        <option value="FoodEstablishment">Restaurant / Cafe [Free]</option>
        <option value="EducationalOrganization">School / University [Free]</option>
        <option value="LodgingBusiness">Hotel / Accommodation [Pro]</option>
        <option value="MedicalClinic">Medical Clinic [Pro]</option>
        <option value="LegalService">Lawyer / Law Firm [Pro]</option>
        <option value="SportsActivityLocation">Gym / Sports Club [Pro]</option>
        <option value="Dentist">Dentist [Pro]</option>
        <option value="RealEstateAgent">Real Estate Agency [Pro]</option>
        <option value="Person">Person / Portfolio [Pro]</option>
        <option value="NewsMediaOrganization">News / Media [Pro]</option>
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
