"""Optional Playwright checks, invoked by senior-school-http.py --browser."""
import os
import tempfile
from pathlib import Path


def check_browser(base, dean, fixture):
    from playwright.sync_api import sync_playwright, expect

    executable = os.environ.get('SRMS_BROWSER_EXECUTABLE')
    if not executable:
        executable = next((str(p) for p in [Path('C:/Program Files/Google/Chrome/Application/chrome.exe'), Path('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe')] if p.exists()), None)
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, **({'executable_path':executable} if executable else {}))
        context = browser.new_context(viewport={'width':1280, 'height':900})
        context.add_cookies([{'name':'PHPSESSID','value':dean,'url':base}])
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(base+'dean-senior-assignments.php')
        navigation = page.locator('#srms-sidebar')
        expect(navigation.get_by_role('link', name='Assign Learners', exact=True)).to_be_visible()
        navigation.get_by_role('link', name='Senior School', exact=True).click()
        expect(navigation.get_by_role('link', name='Assign Learners', exact=True)).not_to_be_visible()
        navigation.get_by_role('link', name='Senior School', exact=True).click()
        expect(navigation.get_by_role('link', name='Assign Learners', exact=True)).to_be_visible()
        form = page.locator('#senior-assign-form')
        submit = form.get_by_role('button', name='Save Assignments')
        expect(submit).to_be_enabled()
        form.locator('[name="PathwayId"]').select_option(str(fixture['pathway']))
        expect(form.locator(f'[name="TrackId"] option[value="{fixture["track"]}"]')).to_have_count(1)
        form.locator('[name="TrackId"]').select_option(str(fixture['track']))
        expect(submit).to_be_enabled()
        subjects = [str(fixture['subjects'][name]) for name in ['Biology','Chemistry']]
        form.locator('[name="Subjects[]"]').select_option(subjects)
        page.get_by_role('checkbox', name='Select Senior learner 0', exact=True).check()
        submit.click()
        expect(page.get_by_role('alert')).to_contain_text('Select exactly 3')
        expect(form.locator('[name="Subjects[]"]')).to_have_values(subjects)
        expect(page.get_by_role('checkbox', name='Select Senior learner 0', exact=True)).to_be_checked()

        page.goto(base+'dean-senior-pathways.php')
        page.get_by_role('button', name='Add Pathway', exact=True).click()
        expect(page.locator('#senior-pathway')).to_be_visible()
        page.locator('#senior-pathway').get_by_role('button', name='Cancel', exact=True).click()
        page.locator('form[data-confirm]').first.get_by_role('button').click()
        expect(page.locator('#senior-confirm')).to_be_visible()
        page.locator('#senior-confirm').get_by_role('button', name='Cancel', exact=True).click()

        page.goto(base+'dean-senior-teachers.php')
        form = page.locator('#senior-teacher-form')
        submit = form.get_by_role('button', name='Save Changes')
        expect(submit).to_be_enabled()
        form.locator('[name="TermId"]').select_option(str(fixture['term']))
        expect(submit).to_be_enabled()
        form.locator('[name="AcademicYearId"]').select_option(str(fixture['years'][1]))
        expect(form.locator(f'[name="TermId"] option[value="{fixture["term"]}"]')).to_have_count(0)
        expect(form.locator('[name="TermId"]')).to_have_value('')
        expect(submit).to_be_enabled()

        page.goto(base+'dean-class-timetable.php')
        page.locator('details.cbe-form > summary').click()
        form = page.locator('form').filter(has=page.locator('[name="action"][value="lesson"]'))
        grade = form.locator('[name="GradeId"] option').filter(has_text='Grade 11').get_attribute('value')
        form.locator('[name="GradeId"]').select_option(grade)
        expect(form.locator('[name="ClassId"] option')).to_have_count(2)
        expect(form.locator(f'[name="ClassId"] option[value="{fixture["classes"][2]}"]')).to_have_count(1)

        for width in [1280,768,320]:
            page.set_viewport_size({'width':width,'height':900})
            page.goto(base+'dean-senior-assignments.php')
            expect(page.locator('#senior-assign-form')).to_be_visible()
            expect(page.locator('#senior-assign-form button[type="submit"]')).to_be_enabled()
            assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'Page overflow at {width}px'
            if width in [1280,320]:
                page.screenshot(path=str(Path(tempfile.gettempdir())/f'srms-senior-{width}.png'), full_page=True)
        assert not errors, errors
        browser.close()
    print('PASS: browser dropdown dependencies, retained invalid form, confirmation modals and responsive widths', flush=True)
