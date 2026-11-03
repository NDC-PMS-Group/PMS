export const soiDevelopmentSource = 'Project Development SOI 2026 Rev. 05';

export const developmentProcedure = (track?: string | null) => {
  if (track === 'spg_ndc_own') return [
    ['8.1.1', 'Project conceptualization', 'AO prepares the concept from ManCom or Board instruction and obtains AGM endorsement.'],
    ['8.1.2', 'ManCom approval to proceed', 'Record the decision, study budget and any required concept revisions.'],
    ['8.1.3', 'Consultancy procurement and study', 'Prepare TOR, budget and MR; obtain approval; record BAC procurement, agreement, any necessary OGCC review, and AGM acceptance of the study.'],
    ['8.1.4', 'ManCom project decision', 'Present the evaluated study. Conditional decisions require compliance and re-presentation before Board endorsement.'],
    ['8.1.5', 'Board approval', 'Record the Board decision. Re-present after conditions are met; changed implementation schemes return to ManCom as appropriate.'],
    ['8.1.6', 'DED and construction procurement', 'Record approved TOR/MR, BAC procurement and award, DED acceptance, construction bidding, legal review and signed agreements.'],
    ['8.1.7', 'Construction implementation and turnover', 'Confirm conditions precedent, monitor construction, and record NDC acceptance and turnover. Record PMU or operations procurement where applicable.'],
  ];
  if (track === 'spg_jv') return [
    ['9', 'Joint Venture procedure', 'Use the latest DEPDev Guidelines and Procedures for Government and Private Entity JV Agreements. Record the applicable guideline/version and approval evidence in Requirements; this SOI does not replace those guidelines.'],
  ];
  return [
    ['7.1', 'Receipt of LOI and project concept', 'AO receives the LOI and concept/pitch deck. The full documentary package follows qualification.'],
    ['7.2', 'Initial review and validation', 'AO checks the investment criteria, philosophy and priorities and submits the initial evaluation report to the AGM.'],
    ['7.3', 'Response to proponent', 'AGM records qualification or disqualification and issues the reply and checklist to a qualified proponent.'],
    ['7.4', 'Receipt of complete requirements', 'AO checks and consolidates the documents. Only AGM/Workgroup Head authorization permits temporary deferral with a reason and deadline at least 14 days before Board presentation.'],
    ['7.5', 'Validation and triangulation', 'AO validates documents, visits the site, discusses the proposal and assesses realistic cost/revenue, viability, feasibility, social/environmental soundness and proponent track record. AGM endorses presentation.'],
    ['7.6', 'ManCom decision and Board endorsement', 'Record approval, disapproval or deferral. Re-present after directives are fulfilled. Record third-party valuation or CBI if instructed.'],
    ['7.7', 'Board decision', 'Complete deferred documents before presentation. Conditional approval stays here until compliance and re-presentation. Changed implementation schemes return to ManCom as appropriate.'],
    ['7.8–7.9', 'Agreement signing and fund release', 'Legal prepares documents and obtains OGCC review as necessary. Record signed agreements and actual releases under their terms before implementation and monitoring.'],
  ];
};
