# Generated from Selenium IDE
# Test name: t15 Assign pack selection DAG
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_15_Assign_pack_selection_DAG:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_15_Assign_pack_selection_DAG(self):
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
    self.driver.find_element(By.NAME, "cat_id").send_keys("packs15")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("$('[name=\"logic\"],[name=\"packfield\"],[name=\"datefield\"],[name=\"countfield\"],[name=\"valuefield\"],[name=\"roles_view\"],[name=\"roles_dags\"],[name=\"roles_invalid\"],[name=\"roles_assign\"],[name=\"roles_add\"],[name=\"roles_edit\"]').css('max-width','250px')")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "trigger").find_element(By.CSS_SELECTOR, "*[value='S']").click()
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
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs15\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert category is saved")
    self.driver.find_element(By.XPATH, "//td//span[contains(.,'packs15')]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs15\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("fd=new FormData($('form[enctype=\"multipart/form-data\"]')[0]);fd.set('packs_upload',new Blob([decodeURIComponent('id,block_id%0A1,1%0A2,1%0A3,2%0A4,2')]));fetch( window.location.href, {body:fd, method:'post'})")
    self.driver.execute_script("//SAVEDESC:Perform upload of CSV data")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(0.5)
    self.driver.execute_script("$('[name=\"pack_id\"]').attr('data-optcount',''+$('[name=\"pack_id\"] option').length)")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"pack_id\"][data-optcount=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert dropdown contains 0 packs")
    self.driver.find_element(By.CSS_SELECTOR, "select[data-optcount=\"1\"]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs15\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-submitset [role=\"tab\"]:nth-of-type(2)").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"1\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc:not([disabled])")) == 0
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"2\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc:not([disabled])")) > 0
    self.driver.find_element(By.NAME, "dag_id").find_element(By.XPATH, "(descendant::option)[. = 'DAG1']").click()
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-packissue button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".mod-packmgmt-okmsg")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(0.5)
    self.driver.execute_script("$('[name=\"pack_id\"]').attr('data-optcount',''+$('[name=\"pack_id\"] option').length)")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"pack_id\"][data-optcount=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert dropdown contains 0 packs")
    self.driver.find_element(By.CSS_SELECTOR, "select[data-optcount=\"1\"]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs15\"]").click()
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
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    self.driver.execute_script("$('[name=\"pack_id\"]').attr('data-optcount',''+$('[name=\"pack_id\"] option').length)")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"pack_id\"][data-optcount=\"3\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"pack_id\"] option[value=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"pack_id\"] option[value=\"2\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert dropdown contains 2 packs")
    self.driver.find_element(By.CSS_SELECTOR, "select[data-optcount=\"3\"]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "select[name=\"pack_id\"]").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-savecontinue')")
    self.driver.execute_script("//SAVEDESC:Save form")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"1\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) == 0
    self.driver.execute_script("//SETDESC:Assert pack ID 1 shown in text box")
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"]").send_keys("SAVESCREENSHOT")
    self.driver.execute_script("//SAVEDESC:Delete form")
    self.driver.execute_script("$('#south').remove();dataEntrySubmit('submit-btn-deleteform')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs15\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Click \"Visit Lab Data\"")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=visit_lab_data\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_count\"][value=\"\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "input[name=\"pack_date\"][value=\"\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert no pack dropdown")
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"]").send_keys("SAVESCREENSHOT")
