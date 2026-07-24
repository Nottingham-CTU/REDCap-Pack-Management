# Generated from Selenium IDE
# Test name: t11 Pack expiry buffer
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_11_Pack_expiry_buffer:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_11_Pack_expiry_buffer(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Pack Management Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Pack Management Test").click()
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("$('[name=\"cat_id\"]').css('max-width','200px')")
    self.driver.find_element(By.NAME, "cat_id").send_keys("packs11")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("$('[name=\"logic\"],[name=\"packfield\"],[name=\"datefield\"],[name=\"countfield\"],[name=\"valuefield\"],[name=\"roles_view\"],[name=\"roles_dags\"],[name=\"roles_invalid\"],[name=\"roles_assign\"],[name=\"roles_add\"],[name=\"roles_edit\"]').css('max-width','250px')")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "trigger").find_element(By.CSS_SELECTOR, "*[value='F']").click()
    self.driver.find_element(By.NAME, "form").find_element(By.CSS_SELECTOR, "*[value='visit_lab_data']").click()
    self.driver.find_element(By.NAME, "dags").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "blocks").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "expire").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "expire_buf").send_keys("10")
    self.driver.find_element(By.NAME, "packfield").find_element(By.CSS_SELECTOR, "*[value='pack_id']").click()
    self.driver.find_element(By.NAME, "datefield").find_element(By.CSS_SELECTOR, "*[value='pack_date']").click()
    self.driver.find_element(By.NAME, "countfield").find_element(By.CSS_SELECTOR, "*[value='pack_count']").click()
    self.driver.find_element(By.NAME, "roles_view").send_keys("PackView")
    self.driver.find_element(By.NAME, "roles_invalid").send_keys("PackInvalid")
    self.driver.find_element(By.NAME, "roles_assign").send_keys("PackAssign")
    self.driver.find_element(By.NAME, "roles_add").send_keys("PackAdd")
    self.driver.find_element(By.NAME, "roles_edit").send_keys("PackEdit")
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs11\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert category is saved")
    self.driver.find_element(By.XPATH, "//td//span[contains(.,'packs11')]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs11\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("expd=new Date();expd.setTime(expd.getTime()+(7*3600000));fd=new FormData($('form[enctype=\"multipart/form-data\"]')[0]);fd.set('packs_upload',new Blob([decodeURIComponent('id,expiry%0A1,')+expd.getFullYear()+'-'+('0'+(1+expd.getMonth())).replace(/.*(.{2})$/,'$1')+'-'+('0'+expd.getDate()).replace(/.*(.{2})$/,'$1')+' '+('0'+expd.getHours()).replace(/.*(.{2})$/,'$1')+':'+('0'+expd.getMinutes()).replace(/.*(.{2})$/,'$1')]));fetch( window.location.href, {body:fd, method:'post'})")
    self.driver.execute_script("//SAVEDESC:Perform upload of CSV data (expiry 7 hours in future)")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    self.driver.execute_script("//SAVEDESC:Save form")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert pack not assigned")
    self.driver.execute_script("//SAVEDESC:Delete form")
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs11\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.NAME, "enabled")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "expire_buf").send_keys("5")
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    self.driver.execute_script("//SAVEDESC:Save form")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) == 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"0\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) == 0
    self.driver.execute_script("//SETDESC:Assert pack assigned")
    self.driver.execute_script("//SAVEDESC:Delete form")
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
