# Generated from Selenium IDE
# Test name: t08 Mark packs invalid
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait
from fn_switchuser import Test_fn_switchuser as Sub1

class Test_08_Mark_packs_invalid:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_08_Mark_packs_invalid(self):
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
    self.driver.find_element(By.NAME, "cat_id").send_keys("packs8")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("$('[name=\"logic\"],[name=\"packfield\"],[name=\"datefield\"],[name=\"countfield\"],[name=\"valuefield\"],[name=\"roles_view\"],[name=\"roles_dags\"],[name=\"roles_invalid\"],[name=\"roles_assign\"],[name=\"roles_add\"],[name=\"roles_edit\"]').css('max-width','250px')")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "trigger").find_element(By.CSS_SELECTOR, "*[value='F']").click()
    self.driver.find_element(By.NAME, "form").find_element(By.CSS_SELECTOR, "*[value='visit_lab_data']").click()
    self.driver.find_element(By.NAME, "dags").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "blocks").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "expire").find_element(By.CSS_SELECTOR, "*[value='0']").click()
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
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs8\"]")) > 0
    self.driver.execute_script("//SETDESC:Assert category is saved")
    self.driver.find_element(By.XPATH, "//td//span[contains(.,'packs8')]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs8\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("fd=new FormData($('form[enctype=\"multipart/form-data\"]')[0]);fd.set('packs_upload',new Blob(['id\\n1\\n2\\n3\\n4']));fetch( window.location.href, {body:fd, method:'post'})")
    self.driver.execute_script("//SAVEDESC:Perform upload of CSV data")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]:not([href*=\"logout=1\"])").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs8\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packinvalid")) > 0
    self.driver.execute_script("//SETDESC:Assert option to mark packs as invalid is present")
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-packinvalid").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"UserRights/index.php\"]").click()
    self.driver.find_element(By.ID, "new_username_assign").send_keys("user1")
    self.driver.find_element(By.ID, "assignUserBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.visibility_of_element_located((By.ID, "notify_email_role")))
    self.driver.execute_script("$('#notify_email_role').prop('checked',false)")
    self.driver.find_element(By.ID, "user_role").find_element(By.XPATH, "(descendant::option)[. = 'PackInvalid']").click()
    self.driver.find_element(By.ID, "assignDagRoleBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user')]")))
    time.sleep(3)
    self.driver.find_element(By.ID, "new_username_assign").send_keys("user2")
    self.driver.find_element(By.ID, "assignUserBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.visibility_of_element_located((By.ID, "notify_email_role")))
    self.driver.execute_script("$('#notify_email_role').prop('checked',false)")
    self.driver.find_element(By.ID, "user_role").find_element(By.XPATH, "(descendant::option)[. = 'PackView']").click()
    self.driver.find_element(By.ID, "assignDagRoleBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user2')]")))
    time.sleep(3)
    self.vars["username"] = "user1"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs8\"]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs8\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packinvalid")) > 0
    self.driver.execute_script("//SETDESC:Assert option to mark packs as invalid is present")
    self.driver.find_element(By.CSS_SELECTOR, ".packmgmt-packinvalid").send_keys("SAVESCREENSHOT")
    self.vars["username"] = "user2"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs8\"]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_list\"][href*=\"cat_id=packs8\"]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".packmgmt-packinvalid")) == 0
    self.driver.execute_script("//SETDESC:Assert option to mark packs as invalid is not present")
    self.driver.find_element(By.CSS_SELECTOR, "input[name=\"pack_id\"][value=\"4\"]").send_keys("SAVESCREENSHOT")
    self.vars["username"] = "admin"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"UserRights/index.php\"]").click()
    self.driver.find_element(By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user2')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "#tooltipBtnRemoveProject button").click()
    self.driver.find_element(By.XPATH, "//button[contains(.,'Remove user')]").click()
    None if len(elements := self.driver.find_elements(By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user2')]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
    self.driver.find_element(By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "#tooltipBtnRemoveProject button").click()
    self.driver.find_element(By.XPATH, "//button[contains(.,'Remove user')]").click()
    None if len(elements := self.driver.find_elements(By.XPATH, "//*[@id='user_rights_roles_table']//td//a[contains(.,'user')]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
