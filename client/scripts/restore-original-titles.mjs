import fs from 'fs';

const fixes = [
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/staff/StaffRecordsView.vue',
    oldTitle: 'Staff Professional Development Records',
    newTitle: 'Staff Records & Physical Original Custody',
    oldSubtitle: 'Approved proposals and finalized trainings for institutional staff capacity development.',
    newSubtitle: 'Dual tracking of electronic staff dossiers alongside physical paper original locations for institutional compliance.',
  },
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/archive/ArchiveListView.vue',
    oldTitle: 'Institutional Physical Archive Registry',
    newTitle: 'Digital Archive & Long-Term Preservation',
    oldSubtitle: 'Browse hard-copy file locations, boxes, shelves, and active/closed collection records.',
    newSubtitle: 'Statutory archival repository, retention schedules, legal holds, and historical preservation for auditable institutional memory.',
  },
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/accreditation/AccreditationEvidenceView.vue',
    oldTitle: 'Accreditation Evidence Repository',
    newTitle: 'Accreditation & Quality Assurance',
    oldSubtitle: 'Quality assurance documentation and accreditation criterion evidence for institutional compliance.',
    newSubtitle: 'Map frozen document versions to official national HERQA quality standards with traceable audit reviews.',
  },
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/projects/ProjectsInnovationView.vue',
    oldTitle: 'Community Engagement & Innovation Projects',
    newTitle: 'Projects & Innovation',
    oldSubtitle: 'University outreach projects, community partnerships, and innovation initiatives.',
    newSubtitle: 'Registered project proposals, innovation grants, and active institutional initiatives.',
  },
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/signatures/SignaturesStampsView.vue',
    oldTitle: 'Digital Signature & Official Stamp Registry',
    newTitle: 'Signatures & Stamps',
    oldSubtitle: 'Manage institutional authority signatures, digital seal images, and approval delegation records.',
    newSubtitle: 'Cryptographic signing requests, authorized signers, and departmental office seal registries.',
  },
  {
    file: 'c:/Users/IT3/Desktop/DMS/client/src/views/reports/ReportsView.vue',
    oldTitle: 'Analytical Reports & Insights',
    newTitle: 'Executive Reports & Statistical Summaries',
    oldSubtitle: 'Pre-configured analytics dashboards for document activity, compliance, and system usage patterns.',
    newSubtitle: 'Institutional analytics, operational correspondence audit, document lifecycle diagnostics, and management dashboards.',
  },
];

fixes.forEach(({ file, oldTitle, newTitle, oldSubtitle, newSubtitle }) => {
  let content = fs.readFileSync(file, 'utf8');
  
  const oldPattern = `title="${oldTitle}" subtitle="${oldSubtitle}"`;
  const newPattern = `title="${newTitle}" subtitle="${newSubtitle}"`;
  
  if (content.includes(oldPattern)) {
    content = content.replace(oldPattern, newPattern);
    fs.writeFileSync(file, content, 'utf8');
    console.log(`✓ ${file.split('/').pop()} - title restored`);
  } else {
    console.log(`✗ ${file.split('/').pop()} - pattern not found`);
  }
});

console.log('\n✓ Original titles restored');
