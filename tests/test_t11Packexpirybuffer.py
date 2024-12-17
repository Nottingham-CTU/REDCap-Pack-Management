# Generated by Selenium IDE
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.desired_capabilities import DesiredCapabilities

class TestT11Packexpirybuffer():
  def setup_method(self, method):
    self.driver = webdriver.Firefox()
    self.vars = {}
  
  def teardown_method(self, method):
    self.driver.quit()
  
  def test_t11Packexpirybuffer(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    elements = self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,\'Pack Management Test\')]")
    assert len(elements) > 0
    self.driver.find_element(By.LINK_TEXT, "Pack Management Test").click()
    time.sleep(2)
    self.driver.execute_script("$(\'#south\').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$(\'#south\').remove()")
    self.driver.execute_script("$(\'input[name=\"cat_id\"]\').val(\'packs11\')")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$(\'#south\').remove()")
    dropdown = self.driver.find_element(By.NAME, "enabled")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='1']").click()
    dropdown = self.driver.find_element(By.NAME, "trigger")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='F']").click()
    dropdown = self.driver.find_element(By.NAME, "form")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='visit_lab_data']").click()
    dropdown = self.driver.find_element(By.NAME, "dags")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='0']").click()
    dropdown = self.driver.find_element(By.NAME, "blocks")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='0']").click()
    dropdown = self.driver.find_element(By.NAME, "expire")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "expire_buf").send_keys("10")
    dropdown = self.driver.find_element(By.NAME, "packfield")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='pack_id']").click()
    dropdown = self.driver.find_element(By.NAME, "datefield")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='pack_date']").click()
    dropdown = self.driver.find_element(By.NAME, "countfield")
    dropdown.find_element(By.CSS_SELECTOR, "*[value='pack_count']").click()
    self.driver.find_element(By.NAME, "roles_view").send_keys("PackView")
    self.driver.find_element(By.NAME, "roles_invalid").send_keys("PackInvalid")
    self.driver.find_element(By.NAME, "roles_assign").send_keys("PackAssign")
    self.driver.find_element(By.NAME, "roles_add").send_keys("PackAdd")
    self.driver.find_element(By.NAME, "roles_edit").send_keys("PackEdit")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    elements = self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs11\"]")
    assert len(elements) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs11\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("expd=new Date();expd.setTime(expd.getTime()+(7*3600000));fd=new FormData($(\'form[enctype=\"multipart/form-data\"]\')[0]);fd.set(\'packs_upload\',new Blob([decodeURIComponent(\'id,expiry%0A1,\')+expd.getFullYear()+\'-\'+(1+expd.getMonth())+\'-\'+expd.getDate()+\' \'+expd.getHours()+\':\'+expd.getMinutes()]));fetch( window.location.href, {body:fd, method:\'post\'})")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$(\'#south\').remove();dataEntrySubmit(\'submit-btn-savecontinue\')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")
    assert len(elements) > 0
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")
    assert len(elements) > 0
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")
    assert len(elements) > 0
    self.driver.execute_script("$(\'#south\').remove();dataEntrySubmit(\'submit-btn-deleteform\')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs11\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.NAME, "enabled")))
    self.driver.execute_script("$(\'#south\').remove()")
    self.driver.find_element(By.NAME, "expire_buf").send_keys("5")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$(\'#south\').remove();dataEntrySubmit(\'submit-btn-savecontinue\')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")
    assert len(elements) == 0
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"0\"]")
    assert len(elements) > 0
    elements = self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")
    assert len(elements) == 0
    self.driver.execute_script("$(\'#south\').remove();dataEntrySubmit(\'submit-btn-deleteform\')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
  
