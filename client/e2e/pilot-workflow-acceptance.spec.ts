import { test, expect, type Page, type BrowserContext } from '@playwright/test'

/**
 * Controlled Internal Pilot E2E Workflow Acceptance Suite (Live Laravel + MySQL API)
 *
 * Covers: login/logout, active organizational unit selection + reload persistence,
 * unit-scoped document creation/metadata/file upload/version creation/download,
 * confidential cross-unit isolation, correspondence registration + ref + inter-office movement/receipt,
 * multi-stage workflow (submit -> reject -> resubmit -> approve), role/permission inspection,
 * archive transfer + legal holds, signatures verification & stamp permission UI,
 * audit event verification, and user deactivation -> immediate session invalidation.
 */
async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded')
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {})
}

async function browserLogin(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login')
  await page.locator('input[type="email"]').waitFor({ state: 'visible', timeout: 20000 })
  await page.fill('input[type="email"]', email)
  await page.fill('input[type="password"]', password)
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard', { timeout: 20000 })
  await settle(page)
}

async function apiJson<T = any>(
  page: Page,
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  path: string,
  body?: Record<string, unknown>
): Promise<{ status: number; data: T }> {
  return page.evaluate(
    async ({ method: m, path: p, body: b }) => {
      const getCookie = (name: string): string => {
        const match = document.cookie
          .split('; ')
          .find((row) => row.startsWith(`${name}=`))
        return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : ''
      }

      if (m !== 'GET') {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
      }

      const headers: Record<string, string> = {
        Accept: 'application/json',
      }
      if (m !== 'GET') {
        headers['X-XSRF-TOKEN'] = getCookie('XSRF-TOKEN')
        if (b) {
          headers['Content-Type'] = 'application/json'
        }
      }

      const res = await fetch(`/api/v1${p}`, {
        method: m,
        credentials: 'include',
        headers,
        body: b ? JSON.stringify(b) : undefined,
      })

      let parsed: any = null
      try {
        parsed = await res.json()
      } catch {
        parsed = null
      }
      return { status: res.status, data: parsed }
    },
    { method, path, body }
  )
}

function buildMinimalPdfBuffer(label: string): Buffer {
  return Buffer.from(
    '%PDF-1.4\n' +
      `% ${label}\n` +
      '1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n' +
      '2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n' +
      '3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]>>\nendobj\n' +
      'xref\n0 4\n' +
      '0000000000 65535 f \n' +
      '0000000009 00000 n \n' +
      '0000000058 00000 n \n' +
      '0000000115 00000 n \n' +
      'trailer\n<</Size 4 /Root 1 0 R>>\nstartxref\n190\n%%EOF'
  )
}

test.describe.serial('WU-DMS Controlled Pilot Workflow Acceptance (Real API)', () => {
  let adminContext: BrowserContext
  let adminPage: Page
  let pilotContext: BrowserContext
  let pilotPage: Page

  const runTag = `${Date.now()}_${Math.floor(Math.random() * 1000)}`
  const pilotEmail = `pilot.e2e.${runTag}@wu.edu.et`
  const pilotPassword = 'PilotPass2026!'

  let unitAId = 0
  let unitBId = 0
  let pilotUserId = 0
  let pilotDocId = 0
  let pilotVersion1Id = 0
  let pilotFile1Id = 0
  let confidentialDocUnitBId = 0
  let confidentialFileUnitBId = 0
  let corrId = 0
  let corrMovementId = 0
  let legalHoldUnitBId = 0

  test.beforeAll(async ({ browser }) => {
    adminContext = await browser.newContext()
    adminPage = await adminContext.newPage()
    await browserLogin(adminPage, 'admin@wu.edu.et', 'Password123!')

    pilotContext = await browser.newContext()
    pilotPage = await pilotContext.newPage()
  })

  test.afterAll(async () => {
    await adminContext?.close().catch(() => {})
    await pilotContext?.close().catch(() => {})
  })

  test('1. Admin provisions scoped pilot user, tests role/permission views, and pilot logs in + switches active unit with reload persistence', async () => {
    // Verify Role Management UI renders cleanly
    await adminPage.goto('/admin/roles')
    await settle(adminPage)
    await expect(adminPage.locator('body')).toContainText(/Role|Permission/i)

    // Discover two distinct organizational units + roles + scopes
    const [unitsRes, rolesRes, scopesRes] = await Promise.all([
      apiJson(adminPage, 'GET', '/organization-units'),
      apiJson(adminPage, 'GET', '/roles'),
      apiJson(adminPage, 'GET', '/authorization-scopes'),
    ])
    expect(unitsRes.status).toBe(200)
    const units = unitsRes.data?.data || []
    expect(units.length).toBeGreaterThanOrEqual(2)

    const unitA = units.find((u: any) => u.code === 'SE') || units[0]
    const unitB = units.find((u: any) => u.id !== unitA.id && u.code === 'EE') || units.find((u: any) => u.id !== unitA.id)
    expect(unitA).toBeTruthy()
    expect(unitB).toBeTruthy()
    unitAId = Number(unitA.id)
    unitBId = Number(unitB.id)

    // Create pilot user in Unit A + assign secondary membership in Unit B (for active-unit UI switch)
    const userRes = await apiJson(adminPage, 'POST', '/users', {
      name: `Pilot Officer ${runTag}`,
      email: pilotEmail,
      password: pilotPassword,
      status: 'active',
      unit_id: unitAId,
      position_title: 'Pilot Departmental Officer',
    })
    expect(userRes.status).toBe(201)
    pilotUserId = Number(userRes.data?.data?.id)
    expect(pilotUserId).toBeGreaterThan(0)

    const memRes = await apiJson(adminPage, 'POST', `/users/${pilotUserId}/memberships`, {
      unit_id: unitBId,
      position_title: 'Visiting Committee Member',
      is_primary: false,
    })
    expect([200, 201]).toContain(memRes.status)

    // Grant records_officer role ONLY on Unit A (NOT Unit B)
    const roles = rolesRes.data?.data || []
    const recordsRole = roles.find((r: any) => r.code === 'records_officer') || roles.find((r: any) => r.code === 'department_head')
    expect(recordsRole).toBeTruthy()

    const scopes = scopesRes.data?.data || []
    const scopeUnitA = scopes.find((s: any) => s.kind === 'unit' && Number(s.unit_id) === unitAId)
    expect(scopeUnitA).toBeTruthy()

    const assignRoleRes = await apiJson(adminPage, 'POST', '/user-roles', {
      user_id: pilotUserId,
      role_id: recordsRole.id,
      scope_kind: 'unit',
      unit_id: unitAId,
    })
    expect([200, 201]).toContain(assignRoleRes.status)

    // Grant correspondence.acknowledge on Unit B so pilot can receive routed inter-office correspondence in Unit B,
    // without holding any write/create/archive/confidential clearance in Unit B
    const permsRes = await apiJson(adminPage, 'GET', '/permissions')
    const perms = permsRes.data?.data || []
    const ackPerm = perms.find((p: any) => p.code === 'correspondence.acknowledge')
    if (ackPerm) {
      await apiJson(adminPage, 'POST', '/user-permissions', {
        user_id: pilotUserId,
        permission_id: ackPerm.id,
        scope_kind: 'unit',
        unit_id: unitBId,
      })
    }

    // Pilot user logs in via real LoginView form
    await browserLogin(pilotPage, pilotEmail, pilotPassword)
    expect(pilotPage.url()).toContain('/dashboard')

    // Switch active organizational unit to Unit B and verify persistence after page reload
    const switchRes = await apiJson(pilotPage, 'POST', '/auth/active-unit', { unit_id: unitBId })
    expect(switchRes.status).toBe(200)
    await pilotPage.reload()
    await settle(pilotPage)
    const meAfterReload = await apiJson(pilotPage, 'GET', '/auth/me')
    expect(meAfterReload.status).toBe(200)
    expect(Number(meAfterReload.data?.user?.active_unit_id ?? meAfterReload.data?.data?.active_unit_id)).toBe(unitBId)

    // Switch active unit back to Unit A and reload UI so Pinia authStore refreshes Unit A capabilities
    const switchBack = await apiJson(pilotPage, 'POST', '/auth/active-unit', { unit_id: unitAId })
    expect(switchBack.status).toBe(200)
    await pilotPage.reload()
    await settle(pilotPage)
  })

  test('2. Unit-scoped document upload, version creation, authorized download, and confidential cross-unit isolation', async () => {
    const metaRes = await apiJson(adminPage, 'GET', '/metadata/all')
    expect(metaRes.status).toBe(200)
    const cats = metaRes.data?.data?.document_categories || []
    const confs = metaRes.data?.data?.confidentiality_levels || []
    const internalConf = confs.find((c: any) => c.code === 'internal') || confs[0]
    const secretConf = confs.find((c: any) => c.code === 'confidential') || confs[confs.length - 1]

    // Navigate UI to /documents/create as pilot user and upload v1 PDF
    await pilotPage.goto('/documents/create')
    await settle(pilotPage)
    const uploadTab = pilotPage.locator('button', { hasText: 'Physical / Digital Scan Intake' })
    await uploadTab.click()
    await pilotPage.waitForSelector('#file-upload', { state: 'attached', timeout: 10000 })

    const docTitle = `Pilot Unit A Doc ${runTag}`
    await pilotPage.locator('form input[type="text"], form input:not([type])').first().fill(docTitle)

    const selects = pilotPage.locator('form select')
    for (let i = 0; i < (await selects.count()); i++) {
      const sel = selects.nth(i)
      const opts = await sel.locator('option').all()
      const hasUnitA = await sel.locator(`option[value="${unitAId}"]`).count()
      const hasInternal = await sel.locator(`option[value="${internalConf.id}"]`).count()
      if (hasUnitA > 0) {
        await sel.selectOption(String(unitAId))
        continue
      }
      if (hasInternal > 0) {
        await sel.selectOption(String(internalConf.id))
        continue
      }
      for (const opt of opts) {
        const val = await opt.getAttribute('value')
        if (val && val !== '') {
          await sel.selectOption(val)
          break
        }
      }
    }

    await pilotPage.locator('input[type="file"]').first().setInputFiles({
      name: `pilot-v1-${runTag}.pdf`,
      mimeType: 'application/pdf',
      buffer: buildMinimalPdfBuffer('v1'),
    })

    const createResPromise = pilotPage.waitForResponse(
      (r) => r.url().includes('/api/v1/documents') && r.request().method() === 'POST',
      { timeout: 15000 }
    )
    await pilotPage.locator('button[type="submit"]').first().click()
    const createRes = await createResPromise
    expect([200, 201]).toContain(createRes.status())
    const createdPayload = await createRes.json()
    pilotDocId = Number(createdPayload?.data?.id)
    pilotVersion1Id = Number(createdPayload?.data?.versions?.[0]?.id || createdPayload?.data?.current_version?.id || 0)
    pilotFile1Id = Number(createdPayload?.data?.versions?.[0]?.files?.[0]?.file_object_id || createdPayload?.data?.versions?.[0]?.files?.[0]?.id || 0)
    expect(pilotDocId).toBeGreaterThan(0)

    // Navigate to DocumentDetailView UI and verify persistence + reload
    await pilotPage.goto(`/documents/${pilotDocId}`)
    await settle(pilotPage)
    await expect(pilotPage.locator('body')).toContainText(docTitle)

    // Upload version 2 via authenticated Sanctum multipart request and verify reload persistence
    const v2Upload = await pilotPage.evaluate(async ({ docId, tag }) => {
      const match = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))
      const xsrf = match ? decodeURIComponent(match.split('=').slice(1).join('=')) : ''
      const fd = new FormData()
      const pdfBytes = new TextEncoder().encode('%PDF-1.4\n% v2\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF')
      fd.append('file', new Blob([pdfBytes], { type: 'application/pdf' }), `pilot-v2-${tag}.pdf`)
      fd.append('change_note', `Pilot E2E revision ${tag}`)
      fd.append('purpose', 'primary')
      const res = await fetch(`/api/v1/documents/${docId}/versions`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' },
        body: fd,
      })
      return { status: res.status, body: await res.json() }
    }, { docId: pilotDocId, tag: runTag })
    expect([200, 201]).toContain(v2Upload.status)

    await pilotPage.reload()
    await settle(pilotPage)
    await expect(pilotPage.locator('body')).toContainText(`Pilot E2E revision ${runTag}`)

    // Verify authorized file download (if file id captured)
    if (pilotFile1Id > 0) {
      const dlStatus = await pilotPage.evaluate(async (fid) => {
        const res = await fetch(`/api/v1/files/${fid}/download`, { credentials: 'include' })
        return res.status
      }, pilotFile1Id)
      expect(dlStatus).toBe(200)
    }

    // Cross-unit confidential isolation: admin creates Unit B confidential document
    const unitBConfCreate = await adminPage.evaluate(async ({ unitId, catId, confId, tag }) => {
      const match = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))
      const xsrf = match ? decodeURIComponent(match.split('=').slice(1).join('=')) : ''
      const fd = new FormData()
      const pdfBytes = new TextEncoder().encode('%PDF-1.4\n% secret\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF')
      fd.append('title', `Unit B Secret ${tag}`)
      fd.append('category_id', String(catId))
      fd.append('confidentiality_id', String(confId))
      fd.append('owner_unit_id', String(unitId))
      fd.append('document_date', new Date().toISOString().slice(0, 10))
      fd.append('file', new Blob([pdfBytes], { type: 'application/pdf' }), 'secret-unit-b.pdf')
      const res = await fetch('/api/v1/documents', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' },
        body: fd,
      })
      return { status: res.status, body: await res.json() }
    }, { unitId: unitBId, catId: cats[0].id, confId: secretConf.id, tag: runTag })
    expect(unitBConfCreate.status).toBe(201)
    confidentialDocUnitBId = Number(unitBConfCreate.body?.data?.id || 0)
    confidentialFileUnitBId = Number(
      unitBConfCreate.body?.data?.versions?.[0]?.files?.[0]?.file_object_id ||
        unitBConfCreate.body?.data?.versions?.[0]?.files?.[0]?.id ||
        0
    )
    expect(confidentialDocUnitBId).toBeGreaterThan(0)

    // Pilot attempts to view Unit B confidential doc in browser -> 403 UI feedback & hidden content
    await pilotPage.goto(`/documents/${confidentialDocUnitBId}`)
    await settle(pilotPage)
    await expect(pilotPage.locator('body')).not.toContainText(`Unit B Secret ${runTag}`)
    await expect(pilotPage.locator('body')).toContainText(/Forbidden|403|Restricted|Access Denied|Unauthorized|not authorized/i)

    if (confidentialFileUnitBId > 0) {
      const forbiddenDl = await pilotPage.evaluate(async (fid) => {
        const res = await fetch(`/api/v1/files/${fid}/download`, { credentials: 'include' })
        return res.status
      }, confidentialFileUnitBId)
      expect(forbiddenDl).toBe(403)
    }

    // Pilot also blocked from creating documents in Unit B via API -> 403
    const crossCreateRes = await apiJson(pilotPage, 'POST', '/documents', {
      title: `Forbidden Cross-Unit ${runTag}`,
      category_id: cats[0].id,
      confidentiality_id: internalConf.id,
      owner_unit_id: unitBId,
      document_date: new Date().toISOString().slice(0, 10),
    })
    expect(crossCreateRes.status).toBe(403)
  })

  test('3. Correspondence registration + reference + inter-office routing & receipt + workflow reject/resubmit/approve', async () => {
    const metaRes = await apiJson(adminPage, 'GET', '/metadata/all')
    const cats = metaRes.data?.data?.document_categories || []
    const confs = metaRes.data?.data?.confidentiality_levels || []
    const corrTypes = metaRes.data?.data?.correspondence_types || []
    const priorities = metaRes.data?.data?.priorities || []

    // Register incoming correspondence in Unit A -> verify WU/CORR/YYYY/NNNNNN reference
    const corrRes = await apiJson(pilotPage, 'POST', '/correspondences', {
      title: `Pilot Inter-Office Dispatch ${runTag}`,
      category_id: cats[0].id,
      confidentiality_id: (confs.find((c: any) => c.code === 'internal') || confs[0]).id,
      owner_unit_id: unitAId,
      type_id: corrTypes[0].id,
      priority_id: priorities[0].id,
      purpose_code: 'official_communication',
      correspondence_date: new Date().toISOString().slice(0, 10),
      first_receiving_unit_id: unitAId,
      sender_external_name: 'Ministry of Education External Desk',
      to_unit_id: unitBId,
    })
    expect(corrRes.status).toBe(201)
    corrId = Number(corrRes.data?.data?.id)
    const corrRef = String(corrRes.data?.data?.document?.reference_number || '')
    expect(corrRef).toMatch(/^WU\/CORR\/\d{4}\/\d{6}$/)

    // Send movement from Unit A -> Unit B, then acknowledge receipt in Unit B
    const moveRes = await apiJson(pilotPage, 'POST', `/correspondences/${corrId}/movements`, {
      from_unit_id: unitAId,
      to_unit_id: unitBId,
      instruction: `Please process pilot review ${runTag}`,
    })
    expect([200, 201]).toContain(moveRes.status)
    corrMovementId = Number(moveRes.data?.data?.id)

    const ackRes = await apiJson(pilotPage, 'POST', `/correspondences/${corrId}/movements/${corrMovementId}/acknowledge`, {
      note: 'Received at Unit B desk',
    })
    expect([200, 201]).toContain(ackRes.status)

    // Open /correspondence UI and reload to verify persistence
    await pilotPage.goto('/correspondence')
    await settle(pilotPage)
    await pilotPage.reload()
    await settle(pilotPage)
    await expect(pilotPage.locator('body')).toContainText(`Pilot Inter-Office Dispatch ${runTag}`)

    // Multi-stage Workflow: start workflow (draft) -> submit -> admin returns for revision -> resubmit -> admin approves + rejects separate run
    const wfDefs = await apiJson(adminPage, 'GET', '/workflows/definitions')
    const def =
      (wfDefs.data?.data || []).find((d: any) => d.code === 'standard_doc_approval') ||
      (wfDefs.data?.data || []).find((d: any) => Number(d.active) === 1 || d.active === true) ||
      wfDefs.data?.data?.[0]
    expect(def).toBeTruthy()

    if (pilotVersion1Id > 0 && def) {
      const startWf = await apiJson(adminPage, 'POST', '/workflows/instances', {
        workflow_definition_id: def.id,
        document_version_id: pilotVersion1Id,
      })
      expect([200, 201]).toContain(startWf.status)
      const wfInstanceId = Number(startWf.data?.data?.id)

      const fetchTaskForInstance = async () => {
        const res = await apiJson(adminPage, 'GET', '/workflows/tasks?status=open&scope=office&per_page=100')
        return (res.data?.data || []).find((t: any) => Number(t.instance_id) === wfInstanceId)
      }

      const task1 = await fetchTaskForInstance()
      if (task1) {
        // Submit from draft -> under_review
        const subRes = await apiJson(adminPage, 'POST', `/workflows/tasks/${task1.id}/decide`, {
          decision: 'submit',
          reason: `Initial submission (${runTag})`,
        })
        expect(subRes.status).toBe(200)

        // Return from under_review -> draft for revision
        const reviewTask = await fetchTaskForInstance()
        expect(reviewTask).toBeTruthy()
        const retRes = await apiJson(adminPage, 'POST', `/workflows/tasks/${reviewTask.id}/decide`, {
          decision: 'return',
          reason: `Needs Annex B update (${runTag})`,
        })
        expect(retRes.status).toBe(200)

        // Resubmit (draft -> under_review) and approve
        const returnedTask = await fetchTaskForInstance()
        expect(returnedTask).toBeTruthy()
        const resubRes = await apiJson(adminPage, 'POST', `/workflows/tasks/${returnedTask.id}/decide`, {
          decision: 'submit',
          reason: `Resubmitted after Annex B update (${runTag})`,
        })
        expect(resubRes.status).toBe(200)

        const approveTask = await fetchTaskForInstance()
        expect(approveTask).toBeTruthy()
        const appRes = await apiJson(adminPage, 'POST', `/workflows/tasks/${approveTask.id}/decide`, {
          decision: 'approve',
          reason: `Verified and approved (${runTag})`,
        })
        expect(appRes.status).toBe(200)
      }

      // Also verify rejection path with mandatory reason on a second workflow instance
      const startWf2 = await apiJson(adminPage, 'POST', '/workflows/instances', {
        workflow_definition_id: def.id,
        document_version_id: pilotVersion1Id,
      })
      if ([200, 201].includes(startWf2.status)) {
        const inst2Id = Number(startWf2.data?.data?.id)
        const findInst2Task = async () => {
          const r = await apiJson(adminPage, 'GET', '/workflows/tasks?status=open&scope=office&per_page=100')
          return (r.data?.data || []).find((t: any) => Number(t.instance_id) === inst2Id)
        }
        const t2 = await findInst2Task()
        if (t2) {
          await apiJson(adminPage, 'POST', `/workflows/tasks/${t2.id}/decide`, { decision: 'submit' })
          const t2Rev = await findInst2Task()
          if (t2Rev) {
            const rejRes = await apiJson(adminPage, 'POST', `/workflows/tasks/${t2Rev.id}/decide`, {
              decision: 'reject',
              reason: `Superseded by approved version (${runTag})`,
            })
            expect(rejRes.status).toBe(200)
          }
        }
      }

      await adminPage.goto('/tasks')
      await settle(adminPage)
      await expect(adminPage.locator('body')).toContainText(/Task|Workflow/i)
    }
  })

  test('4. Archive transfer, legal hold placement & cross-unit denial, signatures UI, and audit history ledger', async () => {
    // Archive transfer & legal hold placement on Unit B confidential document by Admin
    const [colRes, polRes] = await Promise.all([
      apiJson(adminPage, 'GET', '/archive/collections'),
      apiJson(adminPage, 'GET', '/archive/policies'),
    ])
    const cols = colRes.data?.data || []
    const pols = polRes.data?.data || []
    if (cols.length > 0 && pols.length > 0 && confidentialDocUnitBId > 0) {
      const transferRes = await apiJson(adminPage, 'POST', '/archive/transfer', {
        document_id: confidentialDocUnitBId,
        collection_id: cols[0].id,
        retention_policy_id: pols[0].id,
      })
      expect([200, 201]).toContain(transferRes.status)
    }

    const holdRes = await apiJson(adminPage, 'POST', '/archive/legal-holds', {
      code: `E2E-HOLD-${runTag}`,
      reason: 'E2E Pilot statutory preservation hold',
      document_ids: [confidentialDocUnitBId],
    })
    expect(holdRes.status).toBe(201)
    legalHoldUnitBId = Number(holdRes.data?.data?.id)

    // Cross-unit pilot user MUST be denied (403) releasing Unit B's hold
    const unauthRelease = await apiJson(pilotPage, 'POST', `/archive/legal-holds/${legalHoldUnitBId}/release`)
    expect(unauthRelease.status).toBe(403)

    // Admin sees hold on /archive and releases it
    await adminPage.goto('/archive')
    await settle(adminPage)
    const relRes = await apiJson(adminPage, 'POST', `/archive/legal-holds/${legalHoldUnitBId}/release`)
    expect(relRes.status).toBe(200)

    // Signatures UI: verify genuine PKI view & unauthorized user blocked from stamp application
    await adminPage.goto('/signatures')
    await settle(adminPage)
    await expect(adminPage.locator('body')).toContainText(/Signature|PKI|Seal/i)

    const stampDenied = await apiJson(pilotPage, 'POST', '/stamps/apply', {
      document_version_id: pilotVersion1Id,
      stamp_id: 1,
      reason: 'Unauthorized stamp attempt',
    })
    expect([403, 422]).toContain(stampDenied.status)

    // Audit history ledger UI renders genuine recorded events (legal hold release, login)
    await adminPage.goto('/admin/audit')
    await settle(adminPage)
    await adminPage.reload()
    await settle(adminPage)
    await expect(adminPage.locator('body')).toContainText(/archive\.legal_hold_released|auth\.login_success/i)
  })

  test('5. Admin deactivates pilot user -> active session immediately invalidated and login denied', async () => {
    // Before deactivation, pilot session is active
    const beforeMe = await apiJson(pilotPage, 'GET', '/auth/me')
    expect(beforeMe.status).toBe(200)

    // Admin deactivates pilot account via status API
    const deactRes = await apiJson(adminPage, 'PATCH', `/users/${pilotUserId}/status`, {
      status: 'inactive',
    })
    expect(deactRes.status).toBe(200)

    // Pilot browser's active session is immediately rejected (403 Account suspended/inactive)
    const afterMe = await apiJson(pilotPage, 'GET', '/auth/me')
    expect([401, 403]).toContain(afterMe.status)

    await pilotPage.goto('/dashboard')
    await settle(pilotPage)

    // Subsequent login attempt by deactivated user fails cleanly in UI with status error feedback
    await pilotPage.goto('/login')
    await settle(pilotPage)
    await pilotPage.fill('input[type="email"]', pilotEmail)
    await pilotPage.fill('input[type="password"]', pilotPassword)
    await pilotPage.click('button[type="submit"]')
    await pilotPage.waitForTimeout(800)
    expect(pilotPage.url()).toContain('/login')
    await expect(pilotPage.locator('body')).toContainText(/not active|inactive|contact an administrator/i)
  })
})
