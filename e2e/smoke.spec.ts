import { test, expect } from '@playwright/test'

test.describe('Admin Web', () => {
  test('login page loads correctly', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('h1')).toContainText('Command')
    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
  })

  test('login fails with wrong credentials', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@test.com')
    await page.fill('input[type="password"]', 'wrongpassword')
    await page.click('button[type="submit"]')
    await expect(page.locator('text=Invalid')).toBeVisible({ timeout: 10000 })
  })

  test('dashboard shows after login', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@khaalo.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await expect(page.locator('text=Command Center')).toBeVisible({ timeout: 10000 })
  })
})

test.describe('Customer Web', () => {
  test('home page loads with restaurants', async ({ page }) => {
    await page.goto('http://localhost:5174')
    await expect(page.locator('text=KHAALO')).toBeVisible()
  })

  test('search works', async ({ page }) => {
    await page.goto('http://localhost:5174/search')
    await page.fill('input[type="text"]', 'biryani')
    await page.waitForTimeout(2000)
    await expect(page.locator('text=biryani').first()).toBeVisible({ timeout: 10000 })
  })
})

test.describe('API Health', () => {
  test('health endpoint responds', async ({ request }) => {
    const response = await request.get('http://localhost:8001/api/health')
    expect(response.ok()).toBeTruthy()
  })

  test('API docs are accessible', async ({ request }) => {
    const response = await request.get('http://localhost:8001/docs/api')
    expect(response.ok()).toBeTruthy()
  })
})
