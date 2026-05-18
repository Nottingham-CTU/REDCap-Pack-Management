# Generated from Selenium IDE
# Test name: t03 Assign pack form submit DAG
# Comment: Check that a pack is assigned on form submission only when issued to the appropriate DAG
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_03_Assign_pack_form_submit_DAG:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_03_Assign_pack_form_submit_DAG(self):
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
    self.driver.execute_script("$('input[name=\"cat_id\"]').val('packs3')")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "trigger").find_element(By.CSS_SELECTOR, "*[value='F']").click()
    self.driver.find_element(By.NAME, "form").find_element(By.CSS_SELECTOR, "*[value='visit_lab_data']").click()
    self.driver.find_element(By.NAME, "dags").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "dags_rcpt").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "blocks").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "expire").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "packfield").find_element(By.CSS_SELECTOR, "*[value='pack_id']").click()
    self.driver.find_element(By.NAME, "datefield").find_element(By.CSS_SELECTOR, "*[value='pack_date']").click()
    self.driver.find_element(By.NAME, "countfield").find_element(By.CSS_SELECTOR, "*[value='pack_count']").click()
    self.driver.find_element(By.NAME, "roles_view").send_keys("PackView")
    self.driver.find_element(By.NAME, "roles_dags").send_keys("PackIssue")
    self.driver.find_element(By.NAME, "roles_invalid").send_keys("PackInvalid")
    self.driver.find_element(By.NAME, "roles_assign").send_keys("PackAssign")
    self.driver.find_element(By.NAME, "roles_add").send_keys("PackAdd")
    self.driver.find_element(By.NAME, "roles_edit").send_keys("PackEdit")
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs3\"]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs3\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("fd=new FormData($('form[enctype=\"multipart/form-data\"]')[0]);fd.set('packs_upload',new Blob(['id,block_id\\n1,1\\n2,1\\n3,2\\n4,2']));fetch( window.location.href, {body:fd, method:'post'})")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs3\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-submitset [role=\"tab\"]:nth-of-type(2)").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"1\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc:not([disabled])")) == 0
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"2\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc:not([disabled])")) > 0
    time.sleep(2)
    self.driver.find_element(By.NAME, "dag_id").find_element(By.XPATH, "(descendant::option)[. = 'DAG1']").click()
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".mod-packmgmt-okmsg")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs3\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-submitset [role=\"tab\"]:nth-of-type(1)").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"1\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packrcpt button.btn-primaryrc:not([disabled])")) == 0
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"2\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packrcpt button.btn-primaryrc:not([disabled])")) > 0
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-packrcpt button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".mod-packmgmt-okmsg")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) == 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) == 0
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs3\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
