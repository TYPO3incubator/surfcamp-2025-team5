import { test, expect } from '@playwright/test';
import { members } from './utils/members';

for (const index in members) {
    test('Registration ' + members[index].firstName, async ({ page }) => {
        await page.goto('/register-membership');
        if (members[index].titel) {
            await page.getByLabel('Titel').fill(members[index].titel);
        }

        if (members[index].gender) {
            await page.getByLabel('Gender').selectOption(members[index].gender.toString());
        }

        await page.getByLabel('First name').fill(members[index].firstName);
        await page.getByLabel('Last name').fill(members[index].lastName);
        await page.getByLabel('Date of birth').fill(members[index].dateofbirth);
        await page.getByLabel('Street').fill(members[index].street);
        await page.getByLabel('ZIP').fill(members[index].zip);
        await page.getByLabel('City').fill(members[index].city);
        await page.getByLabel('Country').selectOption(members[index].countrycode);
        await page.getByLabel('Email address').fill(members[index].email);
        await page.getByLabel('Phone number').fill(members[index].tel);

        if (members[index].iban) {
            await page.getByLabel('IBAN').fill(members[index].iban);
        }

        if (members[index].bic) {
            await page.getByLabel('BIC').fill(members[index].bic);
        }

        if (members[index].sepa) {
            await page.locator('#sepa').check();
        }

        await page.locator('#password').fill(members[index].password);
        await page.getByLabel('Repeat password').fill(members[index].password);

        await page.locator('#privacy').check();

        await page.evaluate(() => {
            page.locator('.btn[type="submit"]').click();
        });
    });
}

